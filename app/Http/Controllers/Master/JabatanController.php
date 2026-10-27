<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Jabatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JabatanController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('MasterData/Jabatan/Index', [
            'jabatan' => Jabatan::query()
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->withCount(['penugasan as penugasan_aktif_count' => fn ($q) => $q->whereNull('selesai_menjabat')])
                ->orderBy('nama')
                ->get(),
            'kategoriList' => Jabatan::KATEGORI,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        Jabatan::create($this->validated($request, $sekolahId));

        return back()->with('success', 'Jabatan ditambahkan.');
    }

    public function update(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        abort_unless($jabatan->sekolah_id === $sekolahId, 404);

        $jabatan->update($this->validated($request, $sekolahId, $jabatan->id));

        return back()->with('success', 'Jabatan diperbarui.');
    }

    public function destroy(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        abort_unless($jabatan->sekolah_id === $sekolahId, 404);

        // Jabatan yang pernah dipakai tetap dibutuhkan histori penugasan;
        // nonaktifkan alih-alih hapus.
        if ($jabatan->penugasan()->exists()) {
            $jabatan->update(['aktif' => false]);

            return back()->with('success', 'Jabatan dinonaktifkan karena sudah dipakai pada riwayat penugasan.');
        }

        $jabatan->delete();

        return back()->with('success', 'Jabatan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $sekolahId, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'nama' => ['required', 'string', 'max:150'],
            'kategori' => ['nullable', Rule::in(Jabatan::KATEGORI)],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $bentrok = Jabatan::where('sekolah_id', $sekolahId)
            ->where('kode', $validated['kode'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'kode' => 'Kode jabatan sudah dipakai di sekolah ini.',
            ]);
        }

        return $validated + ['sekolah_id' => $sekolahId, 'aktif' => $validated['aktif'] ?? true];
    }
}
