<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approval pengajuan BHP + auto-generation dokumen distribusi.
 *
 * Seluruh langkah berjalan dalam SATU transaction: ubah status, konversi
 * reservasi stok, dan pembuatan dokumen. Bila salah satu gagal, semuanya
 * rollback sehingga tidak ada approval setengah jalan.
 *
 * Lihat docs/requirements/workflow-distribusi-bhp.md.
 */
class DokumenService
{
    /** Dokumen yang selalu dihasilkan dari satu approval. */
    private const JENIS_WAJIB = [
        'nota_permintaan',
        'surat_permintaan',
        'surat_penyaluran',
    ];

    public function __construct(
        private readonly StokService $stok,
        private readonly NomorDokumenService $nomor,
    ) {}

    /**
     * @param  array<string,int>  $jumlahDisetujui  map detail_id => jumlah disetujui
     */
    public function approveDanGenerate(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui = []): Pengajuan
    {
        return DB::transaction(function () use ($pengajuan, $reviewer, $jumlahDisetujui) {
            // applyApprove TIDAK membuka transaction sendiri, sehingga seluruh
            // langkah (approve + generate dokumen) berada di satu transaction.
            $pengajuan = $this->stok->applyApprove($pengajuan, $reviewer, $jumlahDisetujui);

            if ($pengajuan->dokumen()->exists()) {
                throw new RuntimeException('Dokumen untuk pengajuan ini sudah pernah dibuat.');
            }

            $tanggal = $pengajuan->tanggal ?: now();
            $tahun = (int) $tanggal->format('Y');
            $bulan = (int) $tanggal->format('n');

            foreach (self::JENIS_WAJIB as $jenis) {
                Dokumen::create([
                    'bhp_pengajuan_id' => $pengajuan->id,
                    'jenis' => $jenis,
                    'nomor' => $this->nomor->generate($jenis, $pengajuan->sumber_dana, $tahun, $bulan),
                    'tanggal_titimangsa' => $tanggal,
                    'payload' => [
                        'sumber_dana' => $pengajuan->sumber_dana,
                        'sekolah_id' => $pengajuan->sekolah_id,
                    ],
                ]);
            }

            return $pengajuan;
        });
    }
}
