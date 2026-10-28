<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        return Inertia::render('MasterData/Pegawai/Index', [
            'pegawai' => Pegawai::where('sekolah_id', $sekolahId)
                ->withCount('unitAktif')
                ->orderBy('nama_lengkap')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        Pegawai::create($this->validated($request) + ['sekolah_id' => $sekolahId]);

        return back()->with('success', 'Pegawai ditambahkan.');
    }

    public function update(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $pegawai->sekolah_id);
        Gate::authorize('kelolaPegawai', $pegawai);

        $pegawai->update($this->validated($request));

        return back()->with('success', 'Pegawai diperbarui.');
    }

    public function destroy(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $pegawai->sekolah_id);
        Gate::authorize('kelolaPegawai', $pegawai);

        // Soft delete: histori mutasi tetap merujuk pegawai ini.
        $pegawai->delete();

        return back()->with('success', 'Pegawai dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'nip' => ['nullable', 'string', 'max:30'],
            'pangkat_golongan' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
