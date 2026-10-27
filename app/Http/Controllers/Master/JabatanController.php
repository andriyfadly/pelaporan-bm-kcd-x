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
        $sekolahId = $this->resolveSekolahIdWajib($request);

        // Tiga jabatan penandatangan selalu ada: dibuat otomatis saat halaman
        // dibuka, jadi sekolah baru tidak perlu menambah manual.
        Jabatan::pastikanWajib($sekolahId);

        return Inertia::render('MasterData/Jabatan/Index', [
            'jabatan' => Jabatan::query()
                ->where('sekolah_id', $sekolahId)
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

        if ($jabatan->isWajib()) {
            // Kode dan nama tiga jabatan penandatangan dipakai sebagai kunci
            // dokumen (dan label pada PDF), jadi tidak boleh berubah.
            $data = $request->validate([
                'kode' => ['nullable', Rule::in([$jabatan->kode])],
                'nama' => ['nullable', Rule::in([$jabatan->nama])],
                'kategori' => ['nullable', Rule::in(Jabatan::KATEGORI)],
            ], [
                'kode.in' => 'Kode jabatan wajib tidak dapat diubah.',
                'nama.in' => 'Nama jabatan wajib tidak dapat diubah.',
            ]);

            $jabatan->update(['kategori' => ($data['kategori'] ?? null) ?: null]);

            return back()->with('success', 'Kategori jabatan diperbarui.');
        }

        $jabatan->update($this->validated($request, $sekolahId, $jabatan->id));

        return back()->with('success', 'Jabatan diperbarui.');
    }

    public function destroy(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        abort_unless($jabatan->sekolah_id === $sekolahId, 404);

        if ($jabatan->isWajib()) {
            return back()->with('error', 'Jabatan wajib penandatangan tidak dapat dihapus.');
        }

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
