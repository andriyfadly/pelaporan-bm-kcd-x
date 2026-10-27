<?php

namespace App\Services\PencatatanBm;

use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\Master\PenugasanPegawaiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak dokumen inventaris. Data sekolah dan penandatangan dibaca dari master
 * data, lalu snapshot-nya disimpan di payload dokumen — cetak ulang tetap sama
 * setelah koreksi master.
 */
class DokumenInventarisService
{
    public function __construct(
        private readonly QrLabelService $qr,
        private readonly PenugasanPegawaiService $penugasan,
    ) {}

    /**
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function kirRuang(Ruang $ruang, Collection $units): Response
    {
        return $this->pdf('pencatatan-bm.kir', [
            'judul' => 'Kartu Inventaris Ruangan',
            'ruang' => $ruang,
            'units' => $units,
            'sekolah' => $ruang->sekolah,
            'pejabat' => $this->pejabat($ruang->sekolah_id),
            'dicetakPada' => now()->translatedFormat('d F Y'),
        ], 'landscape')->download($this->namaFile('KIR', $ruang->nama_ruang));
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function bukuInventaris(Sekolah $sekolah, Collection $units, int $tahun): Response
    {
        return $this->pdf('pencatatan-bm.buku-inventaris', [
            'judul' => 'Buku Inventaris',
            'tahun' => $tahun,
            'units' => $units,
            'sekolah' => $sekolah,
            'pejabat' => $this->pejabat($sekolah->id),
        ], 'landscape')->download($this->namaFile('Buku-Inventaris', (string) $tahun));
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     */
    public function sensus(Sekolah $sekolah, Collection $units, int $tahun): Response
    {
        return $this->pdf('pencatatan-bm.sensus', [
            'judul' => 'Sensus Aset',
            'tahun' => $tahun,
            'units' => $units,
            'sekolah' => $sekolah,
            'pejabat' => $this->pejabat($sekolah->id),
        ], 'landscape')->download($this->namaFile('Sensus-Aset', (string) $tahun));
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    public function simpan(Dokumen $dokumen, string $view, array $tambahan = []): Dokumen
    {
        $pdf = $this->pdf($view, array_merge([
            'judul' => $this->judul($dokumen->jenis),
            'dokumen' => $dokumen,
            'payload' => $dokumen->payload,
            'sekolah' => $dokumen->sekolah,
            'pejabat' => $this->pejabat($dokumen->sekolah_id),
        ], $tambahan));

        $path = "inventaris/dokumen/{$dokumen->sekolah_id}/{$dokumen->jenis}-{$dokumen->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        $dokumen->update(['file_path' => $path]);

        return $dokumen;
    }

    public function unduh(Dokumen $dokumen): Response
    {
        $pdf = $this->pdf('pencatatan-bm.dokumen', [
            'judul' => $this->judul($dokumen->jenis),
            'dokumen' => $dokumen,
            'payload' => $dokumen->payload,
            'sekolah' => $dokumen->sekolah,
            'pejabat' => $this->pejabat($dokumen->sekolah_id),
        ]);

        return $pdf->download($this->namaFile($this->judul($dokumen->jenis), $dokumen->nomor ?? $dokumen->id));
    }

    public function labelUnit(InventarisUnit $unit): Response
    {
        return $this->pdf('pencatatan-bm.label', [
            'unit' => $unit,
            'label' => $this->qr->dataLabel($unit),
            'qrSvg' => $this->qr->svg($unit),
        ])->download($this->namaFile('Label', $unit->nomor_register));
    }

    public function unduhFoto(Foto $foto): StreamedResponse
    {
        return Storage::disk('public')->download($foto->path);
    }

    /**
     * Snapshot tiga jabatan penandatangan pada tanggal dokumen; dokumen tetap
     * bisa dicetak walau salah satu jabatan belum diisi (tidak seperti
     * approval pengajuan BHP).
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function pejabat(string $sekolahId, ?string $tanggal = null): array
    {
        return $this->penugasan->pejabatPada($sekolahId, $tanggal);
    }

    private function pdf(string $view, array $data, string $orientation = 'portrait')
    {
        return Pdf::loadView($view, $data)->setPaper('a4', $orientation);
    }

    private function namaFile(string $jenis, string $identitas): string
    {
        return trim(str_replace(['/', '\\'], '-', "{$jenis}-{$identitas}")).'.pdf';
    }

    private function judul(string $jenis): string
    {
        return match ($jenis) {
            'bast' => 'Berita Acara Serah Terima',
            'fakta_integritas' => 'Fakta Integritas',
            'surat_pengembalian' => 'Surat Permohonan Pengembalian',
            default => ucwords(str_replace('_', ' ', $jenis)),
        };
    }
}
