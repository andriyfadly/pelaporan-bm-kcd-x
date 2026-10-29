<?php

namespace App\Services\PencatatanBm;

use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

/**
 * QR unit hanya memuat signed URL ke detail unit. Endpoint scan tetap
 * melakukan otorisasi tenant, jadi `sekolah_id` tidak pernah ikut di payload.
 */
class QrLabelService
{
    public function signedUnitUrl(InventarisUnit $unit): string
    {
        return URL::temporarySignedRoute(
            'pencatatan-bm.inventaris.show',
            now()->addYear(),
            ['unit' => $unit->id],
        );
    }

    public function svg(InventarisUnit $unit): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 1), new SvgImageBackEnd));

        return $writer->writeString($this->signedUnitUrl($unit));
    }

    /**
     * Blok data label yang dipakai halaman cetak.
     *
     * @return array<string, string|null>
     */
    public function dataLabel(InventarisUnit $unit): array
    {
        $unit->loadMissing('ruang:id,nama_ruang', 'pegawai:id,nama_lengkap');

        return [
            'register' => $unit->nomor_register,
            'kode_barang' => $unit->kode_barang,
            'nama_barang' => $unit->nama_barang,
            'merk_tipe' => $unit->merk_tipe,
            'asal_perolehan' => $unit->asal_perolehan,
            'sekolah' => $unit->sekolah?->nama_sekolah,
            'ruang' => $unit->ruang?->nama_ruang,
            'pegawai' => $unit->pegawai?->nama_lengkap,
            'kondisi' => InventarisUnit::KONDISI[$unit->kondisi] ?? $unit->kondisi,
        ];
    }

    /**
     * Snapshot unit untuk payload dokumen: cetak ulang memakai data ini,
     * bukan data hidup yang mungkin sudah dikoreksi.
     *
     * @return array<string, mixed>
     */
    public function snapshotUnit(InventarisUnit $unit): array
    {
        $unit->loadMissing('ruang:id,nama_ruang', 'pegawai:id,nama_lengkap', 'realisasi:id,no_spk,ba_no,ba_tgl,sumber_perolehan');

        return [
            'id' => $unit->id,
            'nomor_register' => $unit->nomor_register,
            'kode_barang' => $unit->kode_barang,
            'nama_barang' => $unit->nama_barang,
            'jenis_aset' => $unit->jenis_aset,
            'merk_tipe' => $unit->merk_tipe,
            'no_sertifikat' => $unit->no_sertifikat,
            'asal_perolehan' => $unit->asal_perolehan,
            'satuan' => $unit->satuan,
            'ukuran_bangunan' => $unit->ukuran_bangunan,
            'tanggal_perolehan' => $unit->tanggal_perolehan?->format('Y-m-d'),
            'harga_satuan' => (float) $unit->harga_satuan,
            'nilai_perolehan_unit' => (float) $unit->nilai_perolehan_unit,
            'kondisi' => $unit->kondisi,
            'status' => $unit->status,
            'keterangan' => $unit->keterangan,
            'ruang' => $unit->ruang?->nama_ruang,
            'pegawai' => $unit->pegawai?->nama_lengkap,
            'pegawai_nip' => $unit->pegawai?->nip,
            'realisasi' => [
                'no_spk' => $unit->realisasi?->no_spk,
                'ba_no' => $unit->realisasi?->ba_no,
                'ba_tgl' => $unit->realisasi?->ba_tgl,
                'sumber_perolehan' => $unit->realisasi?->sumber_perolehan,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotMutasi(Mutasi $mutasi): array
    {
        return [
            'id' => $mutasi->id,
            'jenis' => $mutasi->jenis,
            'tanggal' => $mutasi->tanggal?->format('Y-m-d'),
            'asal_kondisi' => $mutasi->asal_kondisi,
            'tujuan_kondisi' => $mutasi->tujuan_kondisi,
            'asal_ruang' => $mutasi->asalRuang?->nama_ruang,
            'tujuan_ruang' => $mutasi->tujuanRuang?->nama_ruang,
            'asal_pegawai' => $mutasi->asalPegawai?->nama_lengkap,
            'tujuan_pegawai' => $mutasi->tujuanPegawai?->nama_lengkap,
            'keterangan' => $mutasi->keterangan,
        ];
    }
}
