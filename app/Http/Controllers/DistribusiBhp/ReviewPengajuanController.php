<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Pengajuan;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\StokService;
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

        $items = Pengajuan::with(['details.barang', 'pemohon'])
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', 'submitted')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('DistribusiBhp/Review/Index', [
            'items' => $items,
        ]);
    }

    public function approve(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

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
            );

            if (! empty($validated['review_notes'])) {
                $pengajuan->update(['review_notes' => $validated['review_notes']]);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan disetujui, stok diperbarui, dan dokumen dibuat.');
    }

    public function reject(Request $request, Pengajuan $pengajuan): RedirectResponse
    {
        $this->authorizeSekolah($request, $pengajuan);

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
    }
}
