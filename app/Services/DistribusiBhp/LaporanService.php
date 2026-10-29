<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Laporan operasional Distribusi BHP.
 *
 * Tabel BHP dipisah fisik per sumber dana (`bhp_bos_*`/`bhp_bopd_*`), jadi BOS
 * dan BOPD tidak mungkin tercampur: laporan dijalankan dengan sumber dana aktif
 * tertentu via {@see SumberDana::dengan()}.
 *
 * Semua laporan menerima {@see Periode} opsional. Tanpa periode = posisi/
 * histori penuh; dengan periode = posisi pada akhir bulan itu. Batas tanggal
 * satu sumber: `Periode::sampai()`, dipakai bersama export dan snapshot PDF.
 */
class LaporanService
{
    public function __construct(private readonly StokService $stok) {}

    /**
     * Laporan stok 4-tier per barang untuk satu sumber dana, scoped sekolah.
     * $sekolahId null = tanpa filter (admin lintas sekolah).
     *
     * @return array<int,array<string,mixed>>
     */
    public function laporanStok(string $sumberDana, ?string $sekolahId = null, ?Periode $periode = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $periode): array {
            $barangs = Barang::orderBy('nama_barang')->get();
            $rekap = $this->stok->rekapPerBarang($barangs, $sekolahId, $periode?->sampai());
            $harga = $this->hargaSatuanPerBarang($barangs->pluck('id')->all(), $sekolahId, $periode);

            return $barangs
                ->map(function (Barang $barang) use ($rekap, $harga): array {
                    $satuanHarga = $harga[(string) $barang->id] ?? 0;

                    // Fisik nyata = barang masuk dikurangi yang sudah disetujui
                    // keluar. Reservasi belum keluar gudang, jadi tidak dikurangi.
                    $fisikNyata = max(0, $rekap[$barang->id]['fisik'] - $rekap[$barang->id]['terpakai']);

                    return [
                        'barang_id' => $barang->id,
                        'kode_barang' => $barang->kode_barang,
                        'nama_barang' => $barang->nama_barang,
                        'satuan' => $barang->satuan,
                        'harga_satuan' => $satuanHarga,
                        ...$rekap[$barang->id],
                        'fisik_nyata' => $fisikNyata,
                        'nilai_total' => $fisikNyata * $satuanHarga,
                    ];
                })
                ->all();
        });
    }

    /**
     * Harga satuan per barang untuk laporan.
     *
     * Sumber utama: rata-rata tertimbang penerimaan efektif (nilai_total /
     * jumlah) sampai akhir periode — mencerminkan harga beli nyata pada periode
     * itu. Cadangan: harga master terakhir bila belum ada penerimaan bernilai.
     *
     * @param  list<string>  $barangIds
     * @return array<string,int>
     */
    private function hargaSatuanPerBarang(array $barangIds, ?string $sekolahId, ?Periode $periode): array
    {
        if ($barangIds === []) {
            return [];
        }

        $agregat = PenerimaanDetail::whereIn('barang_id', $barangIds)
            ->where('jumlah', '>', 0)
            ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->where('sekolah_id', $sekolahId)))
            ->when($periode !== null, fn ($q) => $q->whereHas('penerimaan', fn ($p) => $p->efektifSampai($periode->sampai())))
            ->groupBy('barang_id')
            ->selectRaw('barang_id, sum(nilai_total) as nilai, sum(jumlah) as jumlah')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row['barang_id'] => (int) $row['jumlah'] > 0
                    ? (int) round((int) $row['nilai'] / (int) $row['jumlah'])
                    : 0,
            ]);

        $master = HargaBarang::query()
            ->whereIn('barang_id', $barangIds)
            ->orderBy('created_at')
            ->get(['barang_id', 'harga_satuan'])
            ->groupBy('barang_id')
            ->map(fn ($rows): int => (int) $rows->last()->harga_satuan);

        $hasil = [];
        foreach ($barangIds as $id) {
            $nilai = $agregat[(string) $id] ?? 0;
            $hasil[(string) $id] = $nilai > 0 ? $nilai : (int) ($master[(string) $id] ?? 0);
        }

        return $hasil;
    }

    /**
     * Buku penerimaan: daftar barang masuk per bulan, termasuk saldo awal.
     *
     * Baris periode diambil dari selisih dua snapshot `efektifSampai` —
     * reuse aturan tanggal yang sama dengan laporan stok supaya angka buku
     * dan angka kartu persediaan tidak pernah berbeda.
     *
     * @return array<int,array<string,mixed>>
     */
    public function bukuPenerimaan(string $sumberDana, ?string $sekolahId = null, ?Periode $periode = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $periode): array {
            $ambil = fn (string $sampai) => PenerimaanDetail::query()
                ->with([
                    'penerimaan:id,tanggal_bast,tanggal_titimangsa,no_bast,no_nota_invoice,no_kwitansi,tanggal_pembayaran,penyedia_id,is_saldo_awal,periode_tahun,sekolah_id',
                    'penerimaan.penyedia:id,nama',
                    'barang:id,kode_barang,nama_barang,satuan',
                ])
                ->whereHas('penerimaan', fn ($q) => $q
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                    ->efektifSampai($sampai))
                ->get();

            $sampai = $periode?->sampai();
            $semua = $ambil($sampai ?? now()->toDateString());

            if ($periode === null) {
                $dalamPeriode = $semua;
            } else {
                $sebelum = $ambil($periode->mulai->copy()->subDay()->toDateString())->pluck('id')->all();
                $dalamPeriode = $semua->whereNotIn('id', $sebelum);
            }

            return $dalamPeriode
                ->map(function (PenerimaanDetail $detail) use ($periode): array {
                    $penerimaan = $detail->penerimaan;
                    $barang = $detail->barang;
                    $jumlah = (int) $detail->jumlah;
                    $harga = (int) ($detail->harga_satuan ?? 0);

                    // "Lunas Bayar" = sampai akhir bulan laporan barang sudah
                    // datang tetapi transaksi keuangannya belum terjadi.
                    $batasBayar = $periode?->sampai() ?? now()->toDateString();
                    $tanggalBayar = $penerimaan?->tanggal_pembayaran?->toDateString();

                    return [
                        'tanggal' => $penerimaan?->tanggalEfektif()?->toDateString(),
                        'no_bast' => $penerimaan->no_bast ?? ($penerimaan->is_saldo_awal ? 'Saldo Awal '.$penerimaan->periode_tahun : null),
                        'no_nota_invoice' => $penerimaan?->no_nota_invoice,
                        'penyedia' => $penerimaan?->penyedia?->nama,
                        'kode_barang' => $barang?->kode_barang,
                        'nama_barang' => $barang?->nama_barang,
                        'satuan' => $barang?->satuan,
                        'jumlah' => $jumlah,
                        'harga_satuan' => $harga,
                        'nilai_total' => (int) ($detail->nilai_total ?? $jumlah * $harga),
                        'is_saldo_awal' => (bool) $penerimaan?->is_saldo_awal,
                        'lunas_bayar' => ! $penerimaan?->is_saldo_awal
                            && ($tanggalBayar === null || $tanggalBayar > $batasBayar),
                    ];
                })
                ->sortBy([['tanggal', 'asc'], ['nama_barang', 'asc']])
                ->values()
                ->all();
        });
    }

    /**
     * Buku pengeluaran: barang yang sudah disetujui (approved) per sumber dana,
     * scoped sekolah. $sekolahId null = tanpa filter (admin lintas sekolah).
     *
     * Dengan periode, hanya transaksi bertanggal pengajuan di dalam bulan itu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function bukuPengeluaran(string $sumberDana, ?string $sekolahId = null, ?Periode $periode = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $periode): array {
            return PengajuanDetail::query()
                ->with([
                    'pengajuan:id,tanggal,created_at,nomor_pengajuan,status,sekolah_id,divisi_id',
                    'pengajuan.divisi:id,nama',
                    'pengajuan.dokumen:id,bhp_pengajuan_id,jenis,nomor',
                    'barang:id,kode_barang,nama_barang,satuan,spesifikasi_barang_nusp',
                ])
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('status', 'approved')
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                    ->when($periode !== null, fn ($w) => $w->dalamPeriode($periode)))
                ->whereNotNull('jumlah_disetujui')
                ->get()
                ->map(function (PengajuanDetail $detail) {
                    $pengajuan = $detail->pengajuan;
                    $barang = $detail->barang;
                    $jumlah = (int) $detail->jumlah_disetujui;
                    $harga = (int) $detail->harga_satuan;

                    // `nomor_pengajuan` tidak pernah diisi; nomor Nota Permintaan
                    // yang selalu dibuat saat approval adalah identitas praktis
                    // pengajuan di buku pengeluaran.
                    $nomor = $pengajuan?->nomor_pengajuan
                        ?: $pengajuan?->dokumen->firstWhere('jenis', 'nota_permintaan')?->nomor;

                    return [
                        'pengajuan_id' => $pengajuan?->id,
                        'tanggal' => $pengajuan?->tanggalEfektif()?->toDateString(),
                        'nomor_pengajuan' => $nomor,
                        'nomor_dokumen' => $nomor,
                        'nama_barang' => $barang?->nama_barang,
                        'kode_barang' => $barang?->kode_barang,
                        'nusp' => $barang?->spesifikasi_barang_nusp,
                        'spesifikasi' => $barang?->nama_barang,
                        'satuan' => $barang?->satuan,
                        'jumlah' => $jumlah,
                        'harga_satuan' => $harga,
                        'nilai_total' => $jumlah * $harga,
                        'penerima' => $pengajuan?->divisi?->nama,
                        'keperluan' => $detail->keperluan,
                        'keterangan' => $detail->keperluan,
                    ];
                })
                ->sortBy('tanggal')
                ->values()
                ->all();
        });
    }

    /**
     * Kartu persediaan: mutasi masuk/keluar kronologis dengan saldo berjalan.
     *
     * Tanpa periode: seluruh histori dari saldo 0. Dengan periode: saldo awal
     * dihitung dari mutasi sebelum bulan itu, dan hanya mutasi bulan itu yang
     * ditampilkan — sehingga `saldo_akhir` sama dengan laporan stok periode yang
     * sama.
     *
     * @return array{barang:array<string,mixed>,mutasi:array<int,array<string,mixed>>}
     *
     * @throws RuntimeException bila barang tidak ditemukan.
     */
    public function kartuPersediaan(string $barangId, string $sumberDana, ?string $sekolahId = null, ?Periode $periode = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($barangId, $sekolahId, $periode): array {
            $barang = Barang::find($barangId);

            if (! $barang) {
                throw new RuntimeException('Barang tidak ditemukan.');
            }

            // Mutasi masuk: setiap detail penerimaan menambah stok fisik, scoped
            // TEPAT ke sekolah (tidak ada pool pusat).
            $masuk = PenerimaanDetail::where('barang_id', $barang->id)
                ->whereHas('penerimaan', fn ($q) => $q
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                    ->when($periode !== null, fn ($w) => $w->efektifSampai($periode->sampai())))
                ->with('penerimaan:id,tanggal_bast,created_at,no_bast,sekolah_id,is_saldo_awal,periode_tahun')
                ->get()
                ->map(fn (PenerimaanDetail $d) => [
                    'tanggal' => $d->penerimaan?->tanggalEfektif()?->toDateString(),
                    '_sort' => ($d->penerimaan?->tanggalEfektif()->timestamp ?? 0).'.0.'.str_pad((string) $d->created_at?->timestamp, 12, '0', STR_PAD_LEFT),
                    'jenis' => 'masuk',
                    'referensi' => $d->penerimaan?->no_bast ?: 'Penerimaan',
                    'masuk' => (int) $d->jumlah,
                    'keluar' => 0,
                    'keterangan' => $d->keterangan,
                ]);

            // Mutasi keluar: hanya pengajuan berstatus approved, scoped sekolah.
            $keluar = PengajuanDetail::query()
                ->with([
                    'pengajuan:id,tanggal,created_at,nomor_pengajuan,status,sekolah_id',
                    'pengajuan.dokumen:id,bhp_pengajuan_id,jenis,nomor',
                ])
                ->where('barang_id', $barang->id)
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('status', 'approved')
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId))
                    ->when($periode !== null, fn ($w) => $w->efektifSampai($periode->sampai())))
                ->whereNotNull('jumlah_disetujui')
                ->get()
                ->map(function (PengajuanDetail $detail) {
                    $pengajuan = $detail->pengajuan;
                    $tanggal = $pengajuan?->tanggalEfektif();

                    // Nomor Nota Permintaan = identitas pengajuan yang selalu
                    // ada; UUID mentah tidak layak dicetak di kartu.
                    $referensi = $pengajuan?->nomor_pengajuan
                        ?: $pengajuan?->dokumen->firstWhere('jenis', 'nota_permintaan')?->nomor;

                    return [
                        'tanggal' => $tanggal?->toDateString(),
                        '_sort' => ($tanggal->timestamp ?? 0).'.1.',
                        'jenis' => 'keluar',
                        'referensi' => $referensi,
                        'masuk' => 0,
                        'keluar' => (int) $detail->jumlah_disetujui,
                        'keterangan' => $detail->keperluan,
                    ];
                });

            $mutasi = $masuk->concat($keluar)
                ->sortBy('_sort')
                ->values();

            $saldo = 0;
            $totalMasuk = 0;
            $totalKeluar = 0;
            $saldoAwal = 0;
            $rows = [];

            $mulai = $periode?->mulai->toDateString();

            foreach ($mutasi as $row) {
                $dalamPeriode = $mulai === null || ($row['tanggal'] !== null && $row['tanggal'] >= $mulai);

                if (! $dalamPeriode) {
                    $saldoAwal += $row['masuk'] - $row['keluar'];

                    continue;
                }

                $saldo += $row['masuk'] - $row['keluar'];
                $totalMasuk += $row['masuk'];
                $totalKeluar += $row['keluar'];

                unset($row['_sort']);
                $row['saldo'] = $saldoAwal + $saldo;
                $rows[] = $row;
            }

            return [
                'barang' => [
                    'id' => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'satuan' => $barang->satuan,
                    'sumber_dana' => $barang->sumber_dana,
                    'saldo_awal' => $saldoAwal,
                    'total_masuk' => $totalMasuk,
                    'total_keluar' => $totalKeluar,
                    'saldo_akhir' => $saldoAwal + $saldo,
                ],
                'mutasi' => $rows,
            ];
        });
    }

    /**
     * Rekonsiliasi bulanan per barang: buka saldo, masuk, keluar, tutup saldo,
     * sehingga `tutup bulan N = buka bulan N+1` dapat diperiksa langsung.
     *
     * Saldo awal tahun = seluruh mutasi sebelum 1 Januari DITAMBAH saldo awal
     * tiap periode (<= tahun laporan). Saldo awal memang penambahan stok per
     * tahun, bukan snapshot pengganti — konsisten dengan StokService::fisik().
     *
     * Anomali dicatat bila saldo berjalan negatif atau ada mutasi tanpa tanggal.
     *
     * Filter bulan hanya memotong kolom yang ditampilkan; rantai saldo tetap
     * dihitung sejak Januari sehingga `saldo_awal` = buka bulan pertama rentang.
     *
     * @return array{tahun:int,bulan_awal:int,bulan_akhir:int,sumber_dana:string,barang:array<int,array<string,mixed>>,anomali:array<int,string>}
     */
    public function rekonsiliasiBulanan(string $sumberDana, int $tahun, ?string $sekolahId = null, int $bulanAwal = 1, int $bulanAkhir = 12): array
    {
        $bulanAwal = max(1, min(12, $bulanAwal));
        $bulanAkhir = max($bulanAwal, min(12, $bulanAkhir));

        return SumberDana::dengan($sumberDana, function () use ($sumberDana, $tahun, $sekolahId, $bulanAwal, $bulanAkhir): array {
            $awalTahun = Carbon::create($tahun, 1, 1)->startOfDay();
            $mutasi = [];

            PenerimaanDetail::query()
                ->with(['penerimaan:id,tanggal_bast,created_at,is_saldo_awal,periode_tahun,sekolah_id'])
                ->when($sekolahId !== null, fn ($q) => $q->whereHas('penerimaan', fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->get()
                ->each(function (PenerimaanDetail $detail) use (&$mutasi): void {
                    $penerimaan = $detail->penerimaan;
                    // Saldo awal tidak punya tanggal bast; periodenya yang menentukan.
                    $tanggal = $penerimaan->is_saldo_awal && $penerimaan->periode_tahun
                        ? Carbon::create($penerimaan->periode_tahun, 1, 1)
                        : ($penerimaan->tanggal_bast ?? $penerimaan->created_at);

                    $mutasi[] = [
                        'barang_id' => $detail->barang_id,
                        'tanggal' => $tanggal,
                        'masuk' => (int) $detail->jumlah,
                        'keluar' => 0,
                        'referensi' => $penerimaan?->no_bast ?: 'Penerimaan',
                        'saldo_awal' => (bool) $penerimaan?->is_saldo_awal,
                        'periode' => (int) ($penerimaan->periode_tahun ?? 0),
                    ];
                });

            PengajuanDetail::query()
                ->with('pengajuan:id,tanggal,nomor_pengajuan,status,sekolah_id')
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('status', 'approved')
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->whereNotNull('jumlah_disetujui')
                ->get()
                ->each(function (PengajuanDetail $detail) use (&$mutasi): void {
                    $pengajuan = $detail->pengajuan;

                    $mutasi[] = [
                        'barang_id' => $detail->barang_id,
                        'tanggal' => $pengajuan?->tanggal,
                        'masuk' => 0,
                        'keluar' => (int) $detail->jumlah_disetujui,
                        'referensi' => $pengajuan?->nomor_pengajuan ?: (string) $pengajuan?->id,
                        'saldo_awal' => false,
                        'periode' => 0,
                    ];
                });

            $anomali = [];
            $perBarang = [];

            foreach ($mutasi as $row) {
                if ($row['tanggal'] === null) {
                    $anomali[] = "Mutasi {$row['referensi']} tidak memiliki tanggal, tidak dapat ditempatkan pada bulan mana pun.";

                    continue;
                }

                $barangId = $row['barang_id'];
                $perBarang[$barangId] ??= [
                    'saldo_awal' => 0,
                    'masuk' => array_fill(1, 12, 0),
                    'keluar' => array_fill(1, 12, 0),
                ];

                // Saldo awal tiap periode adalah penambahan stok tersendiri
                // (jumlah tiap tahun berbeda), jadi semuanya dijumlahkan — sama
                // seperti StokService::fisik().
                if ($row['saldo_awal']) {
                    if ($row['periode'] <= $tahun) {
                        $perBarang[$barangId]['saldo_awal'] += $row['masuk'] - $row['keluar'];
                    }

                    continue;
                }

                if ($row['tanggal']->lt($awalTahun)) {
                    $perBarang[$barangId]['saldo_awal'] += $row['masuk'] - $row['keluar'];

                    continue;
                }

                if ($row['tanggal']->year !== $tahun) {
                    continue;
                }

                $bulan = (int) $row['tanggal']->month;
                $perBarang[$barangId]['masuk'][$bulan] += $row['masuk'];
                $perBarang[$barangId]['keluar'][$bulan] += $row['keluar'];
            }

            $barangs = Barang::whereIn('id', array_keys($perBarang))->get()->keyBy('id');
            $hasil = [];

            foreach ($perBarang as $barangId => $data) {
                $barang = $barangs->get($barangId);
                $saldo = $data['saldo_awal'];
                $bulanan = [];
                $sudahAnomali = false;

                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $buka = $saldo;
                    $masuk = $data['masuk'][$bulan];
                    $keluar = $data['keluar'][$bulan];
                    $tutup = $buka + $masuk - $keluar;

                    // Saldo buka bulan pertama rentang bisa sudah negatif karena
                    // mutasi di bulan sebelumnya (di luar rentang) — tetap
                    // dilaporkan supaya tidak tampil sebagai angka polos.
                    if ($bulan === $bulanAwal && $buka < 0) {
                        $anomali[] = sprintf(
                            '%s: saldo buka %s sudah negatif (%d) akibat mutasi bulan sebelumnya.',
                            $barang->nama_barang ?? $barangId,
                            str_pad((string) $bulan, 2, '0', STR_PAD_LEFT),
                            $buka,
                        );
                        $sudahAnomali = true;
                    }

                    // Laporkan hanya titik awal saldo negatif; bulan sesudahnya
                    // ikut negatif karena akumulasi, bukan anomali baru.
                    if ($tutup < 0 && ! $sudahAnomali && $bulan >= $bulanAwal && $bulan <= $bulanAkhir) {
                        $anomali[] = sprintf(
                            '%s bulan %d: keluar %d melebihi stok (saldo menjadi %d).',
                            $barang->nama_barang ?? $barangId,
                            $bulan,
                            $keluar,
                            $tutup,
                        );
                        $sudahAnomali = true;
                    }

                    if ($bulan >= $bulanAwal && $bulan <= $bulanAkhir) {
                        $bulanan[$bulan] = [
                            'buka' => $buka,
                            'masuk' => $masuk,
                            'keluar' => $keluar,
                            'tutup' => $tutup,
                        ];
                    }

                    $saldo = $tutup;
                }

                $hasil[] = [
                    'barang_id' => $barangId,
                    'kode_barang' => $barang?->kode_barang,
                    'nama_barang' => $barang?->nama_barang,
                    'satuan' => $barang?->satuan,
                    'saldo_awal' => $bulanan[$bulanAwal]['buka'],
                    'bulan' => $bulanan,
                    'saldo_akhir' => $bulanan[$bulanAkhir]['tutup'],
                ];
            }

            usort($hasil, fn (array $a, array $b): int => strcmp((string) $a['nama_barang'], (string) $b['nama_barang']));

            return [
                'tahun' => $tahun,
                'bulan_awal' => $bulanAwal,
                'bulan_akhir' => $bulanAkhir,
                'sumber_dana' => $sumberDana,
                'barang' => $hasil,
                'anomali' => $anomali,
            ];
        });
    }
}
