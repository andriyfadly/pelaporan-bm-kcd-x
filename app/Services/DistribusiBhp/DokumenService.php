<?php

namespace App\Services\DistribusiBhp;

use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
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
        private readonly PenugasanPegawaiService $pejabat,
    ) {}

    /**
     * @param  array<string,int>  $jumlahDisetujui  map detail_id => jumlah disetujui
     * @param  string|null  $reviewNotes  catatan reviewer, tersimpan atomic dengan approval
     */
    public function approveDanGenerate(Pengajuan $pengajuan, User $reviewer, array $jumlahDisetujui = [], ?string $reviewNotes = null): Pengajuan
    {
        return SumberDana::dengan($pengajuan->sumber_dana, fn () => DB::transaction(function () use ($pengajuan, $reviewer, $jumlahDisetujui, $reviewNotes) {
            // Kunci pengajuan DULU: gate pejabat dan generate nomor dokumen
            // bergantung pada tanggal & status yang tidak boleh berubah di
            // tengah transaksi (dua klik "Setujui" tidak boleh dua-duanya lolos).
            $terkunci = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

            if ($terkunci->dokumen()->exists()) {
                throw new RuntimeException('Dokumen untuk pengajuan ini sudah pernah dibuat.');
            }

            // Gate pejabat: tiga penandatangan wajib berlaku pada tanggal
            // pengajuan SEBELUM status/reservasi/dokumen berubah.
            $penandatangan = $this->pejabat->snapshotPenandatangan(
                (string) $terkunci->sekolah_id,
                ($terkunci->tanggal ?? now())->toDateString(),
            );

            // applyApprove TIDAK membuka transaction sendiri, sehingga seluruh
            // langkah (approve + generate dokumen) berada di satu transaction.
            $pengajuan = $this->stok->applyApprove($terkunci, $reviewer, $jumlahDisetujui);

            if ($reviewNotes !== null && trim($reviewNotes) !== '') {
                $pengajuan->update(['review_notes' => $reviewNotes]);
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
                        'penandatangan' => $penandatangan,
                    ],
                ]);
            }

            return $pengajuan;
        }, 3));
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

        return SumberDana::dengan($pengajuan->sumber_dana, fn () => DB::transaction(function () use ($pengajuan, $jenis, $tanggal) {
            $pengajuan = Pengajuan::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
            $existing = $pengajuan->dokumen()->where('jenis', $jenis)->first();
            if ($existing) {
                return $existing;
            }

            // Dokumen tambahan hanya untuk pengajuan yang sudah approved.
            if (! $pengajuan->isApproved()) {
                throw new RuntimeException('Dokumen tambahan hanya dapat dibuat untuk pengajuan yang sudah disetujui.');
            }

            $pengajuan->load('details.barang', 'dokumen', 'divisi');
            $tanggalObj = $tanggal ? Carbon::parse($tanggal) : ($pengajuan->tanggal ?: now());

            // Stok Opname adalah snapshot fisik: satu per sekolah per bulan.
            // Tanpa batas ini, dua pengajuan di bulan yang sama menghasilkan dua
            // snapshot berbeda untuk posisi stok yang sama.
            if ($jenis === 'stok_opname') {
                $bulanIni = Dokumen::query()
                    ->where('jenis', 'stok_opname')
                    ->whereYear('tanggal_titimangsa', $tanggalObj->year)
                    ->whereMonth('tanggal_titimangsa', $tanggalObj->month)
                    ->whereHas('pengajuan', fn ($q) => $q
                        ->where('sekolah_id', $pengajuan->sekolah_id)
                        ->whereKeyNot($pengajuan->id))
                    ->exists();

                if ($bulanIni) {
                    throw new InvalidArgumentException(sprintf(
                        'Stok Opname %02d/%d sudah dibuat untuk sekolah ini.',
                        $tanggalObj->month,
                        $tanggalObj->year,
                    ));
                }
            }

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
                'payload' => $this->payloadFor($pengajuan, $jenis, $tanggalObj),
            ]);
        }, 3));
    }

    /**
     * @return array<string,mixed>
     */
    private function payloadFor(Pengajuan $pengajuan, string $jenis, Carbon $tanggalObj): array
    {
        $payload = [
            'sumber_dana' => $pengajuan->sumber_dana,
            'sekolah_id' => $pengajuan->sekolah_id,
            // Snapshot penandatangan per tanggal dokumen (bukan live lookup),
            // sehingga reprint tidak berubah ketika pejabat berganti.
            'penandatangan' => $this->pejabat->snapshotPenandatangan(
                (string) $pengajuan->sekolah_id,
                $tanggalObj->toDateString(),
            ),
        ];

        if ($jenis === 'stok_opname') {
            $payload['items'] = $this->laporan->laporanStok(
                $pengajuan->sumber_dana,
                $pengajuan->sekolah_id,
                Periode::bulan((int) $tanggalObj->format('Y'), (int) $tanggalObj->format('n')),
            );
        } elseif (in_array($jenis, ['buku_pengeluaran', 'buku_penyaluran'], true)) {
            // Buku bulanan: hanya transaksi pada bulan tanggal dokumen, bukan
            // seluruh histori sampai dokumen dibuat.
            $items = $this->laporan->bukuPengeluaran(
                $pengajuan->sumber_dana,
                $pengajuan->sekolah_id,
                Periode::bulan((int) $tanggalObj->format('Y'), (int) $tanggalObj->format('n')),
            );

            if ($jenis === 'buku_penyaluran') {
                // Kolom BAST/SPPB merujuk dokumen penyaluran pengajuan ini.
                $bast = $pengajuan->dokumen->firstWhere('jenis', 'bast');
                $sppb = $pengajuan->dokumen->firstWhere('jenis', 'surat_penyaluran');

                $items = array_map(fn (array $item): array => [
                    ...$item,
                    'bast_tanggal' => $bast?->tanggal_titimangsa?->format('d-m-Y'),
                    'bast_nomor' => $bast?->nomor,
                    'sppb_tanggal' => $sppb?->tanggal_titimangsa?->format('d-m-Y'),
                    'sppb_nomor' => $sppb?->nomor,
                ], $items);
            }

            $payload['items'] = $items;
        }

        return $payload;
    }
}
