<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use Illuminate\Support\Facades\DB;

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
     * Laporan stok 4-tier per barang untuk satu sumber dana.
     *
     * @return array<int,array<string,mixed>>
     */
    public function laporanStok(string $sumberDana): array
    {
        return Barang::where('sumber_dana', $sumberDana)
            ->orderBy('nama_barang')
            ->get()
            ->map(fn (Barang $barang) => [
                'barang_id' => $barang->id,
                'kode_barang' => $barang->kode_barang,
                'nama_barang' => $barang->nama_barang,
                'satuan' => $barang->satuan,
                'fisik' => $this->stok->fisik($barang),
                'terpakai' => $this->stok->terpakai($barang),
                'reservasi' => $this->stok->reservasi($barang),
                'tersedia' => $this->stok->tersedia($barang),
            ])
            ->all();
    }

    /**
     * Buku pengeluaran: barang yang sudah disetujui (approved) per sumber dana.
     *
     * @return array<int,array<string,mixed>>
     */
    public function bukuPengeluaran(string $sumberDana): array
    {
        return DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->join('bhp_barang as b', 'b.id', '=', 'd.barang_id')
            ->where('p.status', 'approved')
            ->where('p.sumber_dana', $sumberDana)
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
     * Kartu barang: profil barang + mutasi masuk (penerimaan) dan keluar (approved).
     *
     * @return array{barang:array<string,mixed>,mutasi:array<int,array<string,mixed>>}
     */
    public function kartuBarang(string $barangId): array
    {
        $barang = Barang::findOrFail($barangId);

        $masuk = PenerimaanDetail::where('barang_id', $barang->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (PenerimaanDetail $d) => [
                'tanggal' => $d->created_at?->toDateString(),
                'jenis' => 'masuk',
                'referensi' => 'Penerimaan',
                'jumlah' => (int) $d->jumlah,
            ]);

        $keluar = DB::table('bhp_pengajuan_detail as d')
            ->join('bhp_pengajuan as p', 'p.id', '=', 'd.bhp_pengajuan_id')
            ->where('d.barang_id', $barang->id)
            ->where('p.status', 'approved')
            ->orderBy('p.tanggal')
            ->get(['p.tanggal', 'p.nomor_pengajuan', 'd.jumlah_disetujui as jumlah'])
            ->map(fn ($row) => [
                'tanggal' => $row->tanggal ? substr((string) $row->tanggal, 0, 10) : null,
                'jenis' => 'keluar',
                'referensi' => $row->nomor_pengajuan,
                'jumlah' => (int) $row->jumlah,
            ]);

        $mutasi = $masuk->concat($keluar)
            ->sortBy('tanggal')
            ->values()
            ->all();

        return [
            'barang' => [
                'id' => $barang->id,
                'kode_barang' => $barang->kode_barang,
                'nama_barang' => $barang->nama_barang,
                'satuan' => $barang->satuan,
                'sumber_dana' => $barang->sumber_dana,
                'fisik' => $this->stok->fisik($barang),
                'terpakai' => $this->stok->terpakai($barang),
                'reservasi' => $this->stok->reservasi($barang),
                'tersedia' => $this->stok->tersedia($barang),
            ],
            'mutasi' => $mutasi,
        ];
    }
}
