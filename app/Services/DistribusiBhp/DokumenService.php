<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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

    /** Dokumen tambahan yang dapat dibuat manual setelah approval. */
    public const JENIS_TAMBAHAN = [
        'bast',
        'bap',
        'stok_opname',
        'buku_pengeluaran',
        'buku_penyaluran',
    ];

    public function __construct(
        private readonly StokService $stok,
        private readonly NomorDokumenService $nomor,
        private readonly LaporanService $laporan,
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

    /**
     * Generate satu dokumen tambahan (BAST, BAP, stok opname, buku) untuk
     * pengajuan yang sudah approved. Idempotent per jenis: memanggil ulang
     * mengembalikan dokumen yang sama (nomor tidak berubah).
     */
    public function generateTambahan(Pengajuan $pengajuan, string $jenis, ?string $tanggal = null): Dokumen
    {
        if (! in_array($jenis, self::JENIS_TAMBAHAN, true)) {
            throw new InvalidArgumentException("Jenis dokumen tambahan tidak dikenal: {$jenis}.");
        }

        return DB::transaction(function () use ($pengajuan, $jenis, $tanggal) {
            $existing = $pengajuan->dokumen()->where('jenis', $jenis)->first();
            if ($existing) {
                return $existing;
            }

            $pengajuan->load('details.barang');
            $tanggalObj = $tanggal ? Carbon::parse($tanggal) : ($pengajuan->tanggal ?: now());

            return Dokumen::create([
                'bhp_pengajuan_id' => $pengajuan->id,
                'jenis' => $jenis,
                'nomor' => $this->nomor->generate(
                    $jenis,
                    $pengajuan->sumber_dana,
                    (int) $tanggalObj->format('Y'),
                    (int) $tanggalObj->format('n'),
                ),
                'tanggal_titimangsa' => $tanggalObj,
                'payload' => $this->payloadFor($pengajuan, $jenis),
            ]);
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function payloadFor(Pengajuan $pengajuan, string $jenis): array
    {
        $payload = [
            'sumber_dana' => $pengajuan->sumber_dana,
            'sekolah_id' => $pengajuan->sekolah_id,
        ];

        if ($jenis === 'stok_opname') {
            $payload['items'] = $this->laporan->laporanStok($pengajuan->sumber_dana);
        } elseif (in_array($jenis, ['buku_pengeluaran', 'buku_penyaluran'], true)) {
            $payload['items'] = $this->laporan->bukuPengeluaran($pengajuan->sumber_dana);
        }

        return $payload;
    }
}
