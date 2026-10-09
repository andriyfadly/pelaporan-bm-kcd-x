<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\BatchDokumen;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\SettingsService;
use App\Services\DistribusiBhp\StokService;
use App\Support\DistribusiBhp\SumberDana;
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

    private const VIEW = [
        'nota_permintaan' => 'distribusi-bhp.nota-permintaan',
        'surat_permintaan' => 'distribusi-bhp.surat-permintaan',
        'surat_penyaluran' => 'distribusi-bhp.surat-penyaluran',
        'bast' => 'distribusi-bhp.bast',
        'bap' => 'distribusi-bhp.bap',
        'stok_opname' => 'distribusi-bhp.stok-opname',
        'buku_pengeluaran' => 'distribusi-bhp.buku-pengeluaran',
        'buku_penyaluran' => 'distribusi-bhp.buku-penyaluran',
    ];

    public function __construct(
        private readonly DokumenService $dokumenService,
        private readonly SettingsService $settings,
        private readonly StokService $stok,
    ) {}

    private const PAPER = [
        'nota_permintaan' => ['paper' => 'a4', 'orientation' => 'portrait'],
        'surat_permintaan' => ['paper' => 'a4', 'orientation' => 'landscape'],
        'surat_penyaluran' => ['paper' => 'a4', 'orientation' => 'portrait'],
        'bast' => ['paper' => 'a4', 'orientation' => 'portrait'],
        'bap' => ['paper' => 'a4', 'orientation' => 'portrait'],
        'stok_opname' => ['paper' => 'a4', 'orientation' => 'portrait'],
        'buku_pengeluaran' => ['paper' => 'a4', 'orientation' => 'landscape'],
        'buku_penyaluran' => ['paper' => 'a4', 'orientation' => 'landscape'],
    ];

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

        return SumberDana::dengan($pengajuan->sumber_dana, function () use ($request, $pengajuan): Response {
            $dokumenList = $pengajuan->dokumen()->orderBy('jenis')->get();

            $batch = BatchDokumen::create([
                'jenis' => 'batch_dokumen',
                'bhp_pengajuan_id' => $pengajuan->id,
                'status' => 'pending',
                'total' => $dokumenList->count(),
                'created_by' => $request->user()->id,
            ]);

            $files = [];
            $berhasil = 0;
            $gagal = 0;
            $pdfs = [];

            foreach ($dokumenList as $dokumen) {
                try {
                    $pdfs[] = $this->render($pengajuan->load('details.barang'), $dokumen);
                    $files[] = ['nomor' => $dokumen->nomor, 'jenis' => $dokumen->jenis, 'status' => 'ok'];
                    $berhasil++;
                } catch (Throwable $e) {
                    $files[] = ['nomor' => $dokumen->nomor, 'jenis' => $dokumen->jenis, 'status' => 'error', 'message' => $e->getMessage()];
                    $gagal++;
                }
            }

            // Gabungkan semua PDF menggunakan dompdf
            $pdf = Pdf::loadHtml(
                view('distribusi-bhp.dokumen-kompilasi', [
                    'pengajuan' => $pengajuan->load('details.barang'),
                    'dokumenList' => $dokumenList,
                    'judulMap' => self::JUDUL,
                    'sekolah' => $pengajuan->sekolah?->nama_sekolah ?? 'Dinas / Administrator',
                    'alamat' => $pengajuan->sekolah?->alamat ?? '',
                    'telepon' => $pengajuan->sekolah?->telepon ?? '',
                    'website' => $pengajuan->sekolah?->website ?? '',
                    'email' => $pengajuan->sekolah?->email ?? '',
                    'kodePos' => $pengajuan->sekolah?->kode_pos ?? '',
                    'pemohon' => $pengajuan->pemohon?->name ?? '-',
                    'penandatangan' => $dokumenList->first()?->payload['penandatangan'] ?? [],
                    'settings' => $this->settings->get($pengajuan->sumber_dana),
                ])->render()
            )->setPaper('a4', 'portrait');

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
        });
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
        $pengajuan->loadMissing('details.barang', 'divisi', 'pemohon', 'dokumen');

        return $this->loadPdf($dokumen->jenis, $pengajuan->sumber_dana, self::VIEW[$dokumen->jenis] ?? 'distribusi-bhp.dokumen', [
            'judul' => self::JUDUL[$dokumen->jenis] ?? ucwords(str_replace('_', ' ', $dokumen->jenis)),
            'dokumen' => $dokumen,
            'pengajuan' => $pengajuan,
            'sekolah' => $pengajuan->sekolah?->nama_sekolah ?? 'Dinas / Administrator',
            'alamat' => $pengajuan->sekolah?->alamat ?? '',
            'telepon' => $pengajuan->sekolah?->telepon ?? '',
            'website' => $pengajuan->sekolah?->website ?? '',
            'email' => $pengajuan->sekolah?->email ?? '',
            'kodePos' => $pengajuan->sekolah?->kode_pos ?? '',
            'pemohon' => $pengajuan->pemohon?->name ?? '-',
            'penandatangan' => $dokumen->payload['penandatangan'] ?? [],
            'settings' => $this->settings->get($pengajuan->sumber_dana),
        ]);
    }

    /** Load PDF dengan paper/orientasi hardcode per jenis. */
    private function loadPdf(string $jenis, string $sumberDana, string $view, array $data)
    {
        $cfg = self::PAPER[$jenis] ?? ['paper' => 'a4', 'orientation' => 'portrait'];

        return Pdf::loadView($view, $data)->setPaper($cfg['paper'], $cfg['orientation']);
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
