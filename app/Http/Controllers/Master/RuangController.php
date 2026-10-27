<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\TenantScope;
use App\Models\Master\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RuangController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        return Inertia::render('MasterData/Ruang/Index', [
            'ruang' => Ruang::where('sekolah_id', $sekolahId)
                ->withCount('unitAktif')
                ->orderBy('nama_ruang')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        Ruang::create($this->validated($request, $sekolahId));

        return back()->with('success', 'Ruang ditambahkan.');
    }

    public function update(Request $request, Ruang $ruang): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $ruang->sekolah_id);
        Gate::authorize('kelolaRuang', $ruang);

        $ruang->update($this->validated($request, $ruang->sekolah_id, $ruang->id));

        return back()->with('success', 'Ruang diperbarui.');
    }

    public function destroy(Request $request, Ruang $ruang): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $ruang->sekolah_id);
        Gate::authorize('kelolaRuang', $ruang);

        // Unit yang masih menempati ruang ini kehilangan penempatannya, tetapi
        // histori mutasi tetap menyimpan jejak (FK nullOnDelete).
        $ruang->delete();

        return back()->with('success', 'Ruang dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $sekolahId, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'nama_ruang' => ['required', 'string', 'max:150'],
            'kode' => ['nullable', 'string', 'max:50'],
        ]);

        $bentrok = Ruang::where('sekolah_id', $sekolahId)
            ->whereRaw('lower(nama_ruang) = ?', [mb_strtolower(trim($validated['nama_ruang']))])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'nama_ruang' => 'Nama ruang sudah dipakai di sekolah ini.',
            ]);
        }

        return $validated + ['sekolah_id' => $sekolahId];
    }
}
