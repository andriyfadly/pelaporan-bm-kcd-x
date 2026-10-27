<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\PencatatanBm\Dokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekap berita acara: koreksi/batal nomor BA. Pembatalan hanya mengosongkan
 * nomor dan menyimpan alasan di payload; dokumennya tetap ada.
 */
class BeritaAcaraController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('PencatatanBm/BeritaAcara/Index', [
            'daftar' => Dokumen::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->where('jenis', 'bast')
                ->latest('created_at')
                ->limit(100)
                ->get(['id', 'nomor', 'tanggal', 'nomor_dibatalkan_pada', 'payload', 'created_at']),
        ]);
    }

    public function update(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $dokumen->sekolah_id);

        $validated = $request->validate([
            'nomor' => ['required', 'string', 'max:150'],
        ]);

        $dokumen->update(['nomor' => $validated['nomor'], 'nomor_dibatalkan_pada' => null]);

        return back()->with('success', 'Nomor berita acara dikoreksi.');
    }

    public function destroy(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $dokumen->sekolah_id);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        $dokumen->update([
            'nomor' => null,
            'nomor_dibatalkan_pada' => now(),
            'payload' => array_merge($dokumen->payload ?? [], [
                'pembatalan' => [
                    'alasan' => $validated['alasan'],
                    'oleh' => $request->user()->name,
                    'pada' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return back()->with('success', 'Nomor berita acara dibatalkan.');
    }
}
