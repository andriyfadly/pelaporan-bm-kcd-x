<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Laporan operasional Distribusi BHP.
 *
 * Semua laporan selalu terfilter sumber dana sehingga BOS dan BOPD tidak
 * pernah tercampur. Lihat docs/requirements/reports-and-documents.md.
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
        $barangs = Barang::where('sumber_dana', $sumberDana)
            ->orderBy('nama_barang')
            ->get();

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
    }

    /**
     * Buku pengeluaran: barang yang sudah disetujui (approved) per sumber dana, scoped sekolah.
     * $sekolahId null = tanpa filter (admin lintas sekolah).
     *
     * @return array<int,array<string,mixed>>
     */
    public function bukuPengeluaran(string $sumberDana, ?string $sekolahId = null): array
    {
        return DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->join('bhp_barang as b', 'b.id', '=', 'd.barang_id')
            ->where('p.status', 'approved')
            ->where('p.sumber_dana', $sumberDana)
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->orderBy('p.tanggal')
            ->get([
                'p.tanggal',
                'p.nomor_pengajuan',
                'b.nama_barang',
                'b.satuan',
                'd.jumlah_disetujui as jumlah',
                'd.keperluan',
            ])
            ->map(fn ($row) => [
                'tanggal' => $row->tanggal ? substr((string) $row->tanggal, 0, 10) : null,
                'nomor_pengajuan' => $row->nomor_pengajuan,
                'nama_barang' => $row->nama_barang,
                'satuan' => $row->satuan,
                'jumlah' => (int) $row->jumlah,
                'keperluan' => $row->keperluan,
            ])
            ->all();
    }

    /**
     * Kartu persediaan: mutasi masuk/keluar kronologis dengan saldo berjalan.
     *
     * Setiap baris mencatat satu mutasi (penerimaan = masuk, pengajuan approved =
     * keluar) beserta saldo setelah mutasi tersebut. Hanya mutasi dengan
     * sumber dana yang sama yang diperhitungkan agar BOS dan BOPD tidak tercampur.
     *
     * @return array{barang:array<string,mixed>,mutasi:array<int,array<string,mixed>>}
     *
     * @throws RuntimeException bila barang tidak ditemukan atau sumber dana tidak cocok.
     */
    public function kartuPersediaan(string $barangId, string $sumberDana, ?string $sekolahId = null): array
    {
        $barang = Barang::find($barangId);

        if (! $barang) {
            throw new RuntimeException('Barang tidak ditemukan.');
        }

        if ($barang->sumber_dana !== $sumberDana) {
            throw new RuntimeException('Sumber dana barang tidak sesuai dengan laporan.');
        }

        // Mutasi masuk: setiap detail penerimaan menambah stok fisik, scoped sekolah
        // (penerimaan milik sekolah + pool pusat tanpa sekolah). Filter sumber dana
        // dan sekolah dikerjakan di database, bukan collection PHP.
        $masuk = PenerimaanDetail::where('barang_id', $barang->id)
            ->whereHas('penerimaan', fn ($p) => $p
                ->where('sumber_dana', $sumberDana)
                ->when($sekolahId !== null, fn ($q) => $q->where(function ($w) use ($sekolahId) {
                    $w->where('sekolah_id', $sekolahId)->orWhereNull('sekolah_id');
                })))
            ->with('penerimaan:id,tanggal_bast,created_at,no_bast,sumber_dana,sekolah_id')
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
        $keluar = DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->where('d.barang_id', $barang->id)
            ->where('p.status', 'approved')
            ->where('p.sumber_dana', $sumberDana)
            ->when($sekolahId !== null, fn ($q) => $q->where('p.sekolah_id', $sekolahId))
            ->orderBy('p.tanggal')
            ->get(['p.tanggal', 'p.nomor_pengajuan', 'p.id as pengajuan_id', 'd.jumlah_disetujui as jumlah', 'd.keperluan'])
            ->map(fn ($row) => [
                'tanggal' => $row->tanggal ? substr((string) $row->tanggal, 0, 10) : null,
                '_sort' => ($row->tanggal ? Carbon::parse($row->tanggal)->timestamp : 0).'.1.',
                'jenis' => 'keluar',
                'referensi' => $row->nomor_pengajuan ?: (string) $row->pengajuan_id,
                'masuk' => 0,
                'keluar' => (int) $row->jumlah,
                'keterangan' => $row->keperluan,
            ]);

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
    }
}
