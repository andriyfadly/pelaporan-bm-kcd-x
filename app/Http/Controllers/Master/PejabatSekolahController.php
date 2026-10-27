<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\PejabatSekolah;
use App\Services\PejabatSekolahService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PejabatSekolahController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly PejabatSekolahService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        $pejabats = PejabatSekolah::where('sekolah_id', $sekolahId)
            ->orderBy('jabatan')
            ->orderByDesc('mulai_menjabat')
            ->get();

        return Inertia::render('MasterData/Pejabat/Index', [
            'pejabats' => $pejabats,
            'jabatanList' => PejabatSekolah::JABATAN,
            'labelJabatan' => PejabatSekolah::LABEL_JABATAN,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'jabatan' => ['required', Rule::in(PejabatSekolah::JABATAN)],
            'jenis_jabatan' => ['nullable', Rule::in(PejabatSekolah::JENIS_JABATAN)],
            'nama' => ['required', 'string', 'max:255'],
            'pangkat_golongan' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:30'],
            'mulai_menjabat' => ['required', 'date'],
            'selesai_menjabat' => ['nullable', 'date', 'after_or_equal:mulai_menjabat'],
        ]);

        try {
            $this->service->simpan($sekolahId, (string) $request->user()->id, $validated);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        activity('sistem')
            ->event('kelola-pejabat-sekolah')
            ->withProperties([
                'ringkasan' => "Tambah pejabat {$validated['jabatan']} ({$validated['nama']})",
                'sekolah_id' => $sekolahId,
            ])
            ->log('kelola-pejabat-sekolah');

        return back()->with('success', 'Pejabat berhasil ditambahkan.');
    }

    public function update(Request $request, PejabatSekolah $pejabat): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        abort_unless($pejabat->sekolah_id === $sekolahId, 403, 'Tidak memiliki akses ke pejabat ini.');

        $validated = $request->validate([
            'jabatan' => ['required', Rule::in(PejabatSekolah::JABATAN)],
            'jenis_jabatan' => ['nullable', Rule::in(PejabatSekolah::JENIS_JABATAN)],
            'nama' => ['required', 'string', 'max:255'],
            'pangkat_golongan' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:30'],
            'mulai_menjabat' => ['required', 'date'],
            'selesai_menjabat' => ['nullable', 'date', 'after_or_equal:mulai_menjabat'],
        ]);

        try {
            $this->service->simpan($sekolahId, (string) $request->user()->id, $validated, $pejabat);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Data pejabat berhasil diperbarui.');
    }

    /** Akhiri masa jabatan; riwayat dipertahankan (soft delete tidak dipakai di sini). */
    public function akhiri(Request $request, PejabatSekolah $pejabat): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'selesai_menjabat' => ['required', 'date'],
        ]);

        try {
            $this->service->akhiriMasaJabatan($sekolahId, (string) $request->user()->id, $pejabat, $validated['selesai_menjabat']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Masa jabatan pejabat diakhiri. Riwayat tetap tersimpan.');
    }
}
