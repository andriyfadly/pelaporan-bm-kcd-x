<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Jabatan;
use App\Models\Master\PenugasanPegawai;
use App\Services\Master\PenugasanPegawaiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PenugasanPegawaiController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly PenugasanPegawaiService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        $penugasan = PenugasanPegawai::query()
            ->with(['pegawai:id,nama_lengkap,nip,pangkat_golongan', 'jabatan:id,kode,nama'])
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->orderBy('jabatan_id')
            ->orderByDesc('mulai_menjabat')
            ->get();

        return Inertia::render('MasterData/Penugasan/Index', [
            'penugasan' => $penugasan,
            'jabatanList' => Jabatan::tersedia($sekolahId)->get(['id', 'kode', 'nama']),
            'jenisList' => Jabatan::JENIS_PENUGASAN,
            'labelJenis' => PenugasanPegawai::LABEL_JENIS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        try {
            $this->service->simpan($sekolahId, (string) $request->user()->id, $this->validated($request, $sekolahId));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        activity('sistem')
            ->event('kelola-penugasan-pegawai')
            ->withProperties(['ringkasan' => 'Tambah penugasan pegawai', 'sekolah_id' => $sekolahId])
            ->log('kelola-penugasan-pegawai');

        return back()->with('success', 'Penugasan berhasil disimpan.');
    }

    public function update(Request $request, PenugasanPegawai $penugasan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        abort_unless($penugasan->sekolah_id === $sekolahId, 404);

        try {
            $this->service->simpan(
                $sekolahId,
                (string) $request->user()->id,
                $this->validated($request, $sekolahId),
                $penugasan,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Penugasan diperbarui.');
    }

    public function akhiri(Request $request, PenugasanPegawai $penugasan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'selesai_menjabat' => ['required', 'date'],
        ]);

        try {
            $this->service->akhiri($sekolahId, (string) $request->user()->id, $penugasan, $validated['selesai_menjabat']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Masa jabatan diakhiri. Riwayat tetap tersimpan.');
    }

    public function mutasi(Request $request, PenugasanPegawai $penugasan): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'jabatan' => $this->kodeJabatanRule($sekolahId),
            'jenis_penugasan' => ['nullable', Rule::in(Jabatan::JENIS_PENUGASAN)],
            'mulai_menjabat' => ['required', 'date'],
            'selesai_menjabat' => ['nullable', 'date', 'after_or_equal:mulai_menjabat'],
        ]);

        try {
            $this->service->mutasi($sekolahId, (string) $request->user()->id, $penugasan, $validated);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Pegawai dimutasikan ke jabatan baru.');
    }

    /**
     * Kode jabatan boleh berasal dari master sekolah atau dari tiga jabatan
     * penandatangan bawaan (dibuat otomatis saat penugasan pertama disimpan),
     * supaya sekolah baru bisa langsung mengisi pejabat tanpa setup manual.
     *
     * @return array<int, mixed>
     */
    private function kodeJabatanRule(string $sekolahId): array
    {
        $kode = Jabatan::where('sekolah_id', $sekolahId)->pluck('kode')->all();

        return [
            'required', 'string', 'max:50',
            Rule::in(array_values(array_unique([...$kode, ...Jabatan::KODE_PENANDATANGAN]))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $sekolahId): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'pangkat_golongan' => ['nullable', 'string', 'max:50'],
            'jabatan' => $this->kodeJabatanRule($sekolahId),
            'jenis_penugasan' => ['nullable', Rule::in(Jabatan::JENIS_PENUGASAN)],
            'mulai_menjabat' => ['required', 'date'],
            'selesai_menjabat' => ['nullable', 'date', 'after_or_equal:mulai_menjabat'],
        ]);
    }
}
