<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Support\DistribusiBhp\SumberDana;
use RuntimeException;

/**
 * Laporan operasional Distribusi BHP.
 *
 * Tabel BHP dipisah fisik per sumber dana (`bhp_bos_*`/`bhp_bopd_*`), jadi BOS
 * dan BOPD tidak mungkin tercampur: laporan dijalankan dengan sumber dana aktif
 * tertentu via {@see SumberDana::dengan()}.
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
    public function laporanStok(string $sumberDana, ?string $sekolahId = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($sekolahId): array {
            $barangs = Barang::orderBy('nama_barang')->get();
            $rekap = $this->stok->rekapPerBarang($barangs, $sekolahId);

            return $barangs
                ->map(fn (Barang $barang) => [
                    'barang_id' => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'satuan' => $barang->satuan,
                    ...$rekap[$barang->id],
                ])
                ->all();
        });
    }

    /**
     * Buku pengeluaran: barang yang sudah disetujui (approved) per sumber dana, scoped sekolah.
     * $sekolahId null = tanpa filter (admin lintas sekolah).
     *
     * @return array<int,array<string,mixed>>
     */
    public function bukuPengeluaran(string $sumberDana, ?string $sekolahId = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($sekolahId): array {
            return PengajuanDetail::query()
                ->with('pengajuan:id,tanggal,nomor_pengajuan,status,sekolah_id')
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('status', 'approved')
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->whereNotNull('jumlah_disetujui')
                ->get()
                ->map(function (PengajuanDetail $detail) {
                    $pengajuan = $detail->pengajuan;
                    $barang = $detail->barang;

                    return [
                        'tanggal' => $pengajuan?->tanggal?->toDateString(),
                        'nomor_pengajuan' => $pengajuan?->nomor_pengajuan,
                        'nama_barang' => $barang?->nama_barang,
                        'satuan' => $barang?->satuan,
                        'jumlah' => (int) $detail->jumlah_disetujui,
                        'keperluan' => $detail->keperluan,
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
     * Setiap baris mencatat satu mutasi (penerimaan = masuk, pengajuan approved =
     * keluar) beserta saldo setelah mutasi tersebut. Karena tabel sudah terpisah
     * per sumber dana, mutasi BOS dan BOPD tidak mungkin tercampur.
     *
     * @return array{barang:array<string,mixed>,mutasi:array<int,array<string,mixed>>}
     *
     * @throws RuntimeException bila barang tidak ditemukan.
     */
    public function kartuPersediaan(string $barangId, string $sumberDana, ?string $sekolahId = null): array
    {
        return SumberDana::dengan($sumberDana, function () use ($barangId, $sekolahId): array {
            $barang = Barang::find($barangId);

            if (! $barang) {
                throw new RuntimeException('Barang tidak ditemukan.');
            }

            // Mutasi masuk: setiap detail penerimaan menambah stok fisik, scoped
            // TEPAT ke sekolah (tidak ada pool pusat).
            $masuk = PenerimaanDetail::where('barang_id', $barang->id)
                ->whereHas('penerimaan', fn ($q) => $q
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->with('penerimaan:id,tanggal_bast,created_at,no_bast,sekolah_id')
                ->get()
                ->map(fn (PenerimaanDetail $d) => [
                    'tanggal' => $d->penerimaan?->tanggal_bast?->toDateString()
                        ?? $d->penerimaan?->created_at?->toDateString(),
                    '_sort' => ($d->penerimaan?->tanggal_bast?->timestamp ?? $d->penerimaan?->created_at?->timestamp ?? 0).'.0.'.str_pad((string) $d->created_at?->timestamp, 12, '0', STR_PAD_LEFT),
                    'jenis' => 'masuk',
                    'referensi' => $d->penerimaan?->no_bast ?: 'Penerimaan',
                    'masuk' => (int) $d->jumlah,
                    'keluar' => 0,
                    'keterangan' => $d->keterangan,
                ]);

            // Mutasi keluar: hanya pengajuan berstatus approved, scoped sekolah.
            $keluar = PengajuanDetail::query()
                ->with('pengajuan:id,tanggal,nomor_pengajuan,status,sekolah_id')
                ->where('barang_id', $barang->id)
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('status', 'approved')
                    ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->get()
                ->map(function (PengajuanDetail $detail) {
                    $pengajuan = $detail->pengajuan;
                    $tanggal = $pengajuan?->tanggal;

                    return [
                        'tanggal' => $tanggal?->toDateString(),
                        '_sort' => ($tanggal?->timestamp ?? 0).'.1.',
                        'jenis' => 'keluar',
                        'referensi' => $pengajuan?->nomor_pengajuan ?: (string) $pengajuan?->id,
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
            $rows = [];

            foreach ($mutasi as $row) {
                $saldo += $row['masuk'] - $row['keluar'];
                $totalMasuk += $row['masuk'];
                $totalKeluar += $row['keluar'];

                unset($row['_sort']);
                $row['saldo'] = $saldo;
                $rows[] = $row;
            }

            return [
                'barang' => [
                    'id' => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'satuan' => $barang->satuan,
                    'sumber_dana' => $barang->sumber_dana,
                    'saldo_awal' => 0,
                    'total_masuk' => $totalMasuk,
                    'total_keluar' => $totalKeluar,
                    'saldo_akhir' => $saldo,
                ],
                'mutasi' => $rows,
            ];
        });
    }
}
