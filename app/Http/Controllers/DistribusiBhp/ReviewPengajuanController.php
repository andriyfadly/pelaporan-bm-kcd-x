<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\PejabatSekolah;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\StokService;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReviewPengajuanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly StokService $stok,
        private readonly DokumenService $dokumen,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::normalisasi((string) $request->query('sumber_dana', SumberDana::BOS));

        return SumberDana::dengan($sumberDana, fn (): Response => $this->daftarReview($sekolahId, $sumberDana));
    }

    private function daftarReview(?string $sekolahId, string $sumberDana): Response
    {
        $items = Pengajuan::with(['details.barang', 'pemohon'])
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', 'submitted')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // Peringatan dini bila pejabat penandatangan belum lengkap (approval
        // tetap divalidasi ulang di service).
        $pejabatWarning = null;
        if ($sekolahId && $items->isNotEmpty()) {
            $kurang = collect(PejabatSekolah::JABATAN)
                ->filter(fn ($jabatan) => ! PejabatSekolah::aktif($sekolahId, $jabatan)->exists())
                ->map(fn ($jabatan) => PejabatSekolah::LABEL_JABATAN[$jabatan])
                ->values();

            if ($kurang->isNotEmpty()) {
                $pejabatWarning = 'Pejabat penandatangan belum lengkap: '.$kurang->implode(', ');
            }
        }

        return Inertia::render('DistribusiBhp/Review/Index', [
            'items' => $items,
            'pejabatWarning' => $pejabatWarning,
        ]);
    }

    public function approve(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);
        $this->pastikanBukanPembuat($request, $pengajuan);

        $validated = $request->validate([
            'review_notes' => 'nullable|string',
            'jumlah_disetujui' => 'nullable|array',
            'jumlah_disetujui.*' => 'integer|min:0',
        ]);

        try {
            $this->dokumen->approveDanGenerate(
                $pengajuan->load('details'),
                $request->user(),
                $validated['jumlah_disetujui'] ?? [],
                $validated['review_notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan disetujui, stok diperbarui, dan dokumen dibuat.');
    }

    public function reject(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);
        $this->pastikanBukanPembuat($request, $pengajuan);

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        try {
            $this->stok->reject($pengajuan->load('details'), $request->user(), $validated['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan ditolak dan reservasi stok dilepas.');
    }

    private function authorizeSekolah(Request $request, Pengajuan $pengajuan): void
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId && $pengajuan->sekolah_id !== $sekolahId) {
            abort(403, 'Tidak memiliki akses ke pengajuan ini.');
        }

        $divisiId = $request->user()->divisi_id;
        if ($divisiId && $pengajuan->divisi_id !== $divisiId) {
            abort(403, 'Pengajuan ini bukan milik divisi Anda.');
        }
    }

    private function pastikanBukanPembuat(Request $request, Pengajuan $pengajuan): void
    {
        if ((string) $pengajuan->user_id === (string) $request->user()->id) {
            abort(403, 'Anda tidak dapat menyetujui/menolak pengajuan Anda sendiri.');
        }
    }
}
