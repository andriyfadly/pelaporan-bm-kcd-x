<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\BatchDokumen;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DokumenController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly DokumenService $dokumenService,
        private readonly SettingsService $settings,
    ) {}

    /** Label jenis dokumen dalam Bahasa Indonesia. */
    private const JUDUL = [
        'nota_permintaan' => 'Nota Permintaan Barang',
        'surat_permintaan' => 'Surat Permintaan Barang',
        'surat_penyaluran' => 'Surat Perintah Penyaluran Barang',
        'bast' => 'Berita Acara Serah Terima',
        'bap' => 'Berita Acara Pemeriksaan',
        'stok_opname' => 'Berita Acara Stok Opname',
        'buku_pengeluaran' => 'Buku Pengeluaran Barang',
        'buku_penyaluran' => 'Buku Penyaluran Barang',
    ];

    public function show(Request $request, Pengajuan $pengajuan): \Inertia\Response
    {
        $this->authorizeSekolah($request, $pengajuan);

        return Inertia::render('DistribusiBhp/Dokumen/Show', [
            'pengajuan' => $pengajuan->load(['details.barang', 'dokumen']),
        ]);
    }

    public function pdf(Request $request, Pengajuan $pengajuan, Dokumen $dokumen): Response
    {
        $this->authorizeSekolah($request, $pengajuan);
        $this->authorizeDokumen($pengajuan, $dokumen);

        $pdf = $this->render($pengajuan, $dokumen);

        return $pdf->download($this->safeFilename($dokumen->nomor));
    }

    public function batch(Request $request, Pengajuan $pengajuan): Response
    {
        $this->authorizeSekolah($request, $pengajuan);

        $dokumenList = $pengajuan->dokumen()->orderBy('jenis')->get();

        $batch = BatchDokumen::create([
            'jenis' => 'batch_dokumen',
            'sumber_dana' => $pengajuan->sumber_dana,
            'bhp_pengajuan_id' => $pengajuan->id,
            'status' => 'pending',
            'total' => $dokumenList->count(),
            'created_by' => $request->user()->id,
        ]);

        $files = [];
        $berhasil = 0;
        $gagal = 0;

        foreach ($dokumenList as $dokumen) {
            try {
                $this->render($pengajuan->load('details.barang'), $dokumen)->output();
                $files[] = ['nomor' => $dokumen->nomor, 'jenis' => $dokumen->jenis, 'status' => 'ok'];
                $berhasil++;
            } catch (Throwable $e) {
                $files[] = ['nomor' => $dokumen->nomor, 'jenis' => $dokumen->jenis, 'status' => 'error', 'message' => $e->getMessage()];
                $gagal++;
            }
        }

        // Gabungkan semua dokumen menjadi satu PDF untuk diunduh.
        $pdf = $this->loadPdf('distribusi-bhp.dokumen-kompilasi', [
            'pengajuan' => $pengajuan->load('details.barang'),
            'dokumenList' => $dokumenList,
            'judulMap' => self::JUDUL,
            'sekolah' => $pengajuan->sekolah?->nama_sekolah ?? 'Dinas / Administrator',
            'pemohon' => $pengajuan->pemohon?->name ?? '-',
            'penandatangan' => $dokumenList->first()?->payload['penandatangan'] ?? [],
            'settings' => $this->settings->get(),
        ]);

        DB::transaction(function () use ($batch, $files, $berhasil, $gagal) {
            $batch->update([
                'status' => $gagal === 0 ? 'selesai' : ($berhasil === 0 ? 'gagal' : 'sebagian'),
                'berhasil' => $berhasil,
                'gagal' => $gagal,
                'files' => $files,
                'error_message' => $gagal > 0 ? "{$gagal} dokumen gagal dibuat." : null,
            ]);
        });

        activity('sistem')
            ->event('batch-dokumen-bhp')
            ->performedOn($pengajuan)
            ->withProperties(['batch_id' => $batch->id, 'berhasil' => $berhasil, 'gagal' => $gagal])
            ->log('batch-dokumen-bhp');

        return $pdf->download("batch_dokumen_bhp_{$pengajuan->sumber_dana}.pdf");
    }

    public function tambahan(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

        $validated = $request->validate([
            'jenis' => ['required', Rule::in(DokumenService::JENIS_TAMBAHAN)],
            'tanggal' => ['nullable', 'date'],
        ]);

        try {
            $dokumen = $this->dokumenService->generateTambahan($pengajuan, $validated['jenis'], $validated['tanggal'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Dokumen {$dokumen->nomor} berhasil dibuat.");
    }

    private function render(Pengajuan $pengajuan, Dokumen $dokumen)
    {
        return $this->loadPdf('distribusi-bhp.dokumen', [
            'judul' => self::JUDUL[$dokumen->jenis] ?? ucwords(str_replace('_', ' ', $dokumen->jenis)),
            'dokumen' => $dokumen,
            'pengajuan' => $pengajuan,
            'sekolah' => $pengajuan->sekolah?->nama_sekolah ?? 'Dinas / Administrator',
            'pemohon' => $pengajuan->pemohon?->name ?? '-',
            // Snapshot penandatangan dari payload (bukan pejabat aktif kini),
            // sehingga reprint dokumen lama tetap konsisten.
            'penandatangan' => $dokumen->payload['penandatangan'] ?? [],
            'settings' => $this->settings->get(),
        ]);
    }

    /** Load PDF dengan paper/orientasi dari settings BHP. */
    private function loadPdf(string $view, array $data)
    {
        ['paper' => $paper, 'orientation' => $orientation] = $this->settings->pdf();

        return Pdf::loadView($view, $data)->setPaper($paper, $orientation);
    }

    /** Ubah nomor dokumen menjadi nama file yang aman (tanpa '/' atau '\'). */
    private function safeFilename(string $nomor): string
    {
        return str_replace(['/', '\\'], '-', $nomor).'.pdf';
    }

    private function authorizeSekolah(Request $request, Pengajuan $pengajuan): void
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId && $pengajuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses ke dokumen ini.');
        }

        // Operator divisi hanya boleh menyentuh dokumen pengajuan divisinya sendiri.
        $divisiId = $request->user()->divisi_id;
        if ($divisiId && $pengajuan->divisi_id !== $divisiId) {
            abort(403, 'Dokumen ini bukan milik divisi Anda.');
        }
    }

    private function authorizeDokumen(Pengajuan $pengajuan, Dokumen $dokumen): void
    {
        if ($dokumen->bhp_pengajuan_id !== $pengajuan->id) {
            abort(404, 'Dokumen tidak ditemukan pada pengajuan ini.');
        }
    }
}
