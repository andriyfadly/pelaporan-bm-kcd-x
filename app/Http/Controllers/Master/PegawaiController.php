<?php

namespace App\Http\Controllers\Master;

use App\Exports\TemplateExport;
use App\Http\Controllers\Concerns\ImportsBaris;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Pegawai;
use App\Support\NormalisasiTeks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PegawaiController extends Controller
{
    use ImportsBaris, ResolvesSekolah, TenantScope;

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

    /** Tambah banyak baris sekaligus (acuan: data_pegawai.php). */
    public function storeMassal(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $validated = $request->validate([
            'baris' => ['required', 'array', 'min:1', 'max:100'],
            'baris.*.nama_lengkap' => ['required', 'string', 'max:150'],
            'baris.*.nip' => ['nullable', 'string', 'max:30'],
            'baris.*.pangkat_golongan' => ['nullable', 'string', 'max:50'],
        ]);

        $baris = collect($validated['baris'])
            ->map(fn (array $row): array => [
                'nama_lengkap' => NormalisasiTeks::namaPegawai($row['nama_lengkap']),
                'nip' => NormalisasiTeks::nip($row['nip'] ?? null),
                'pangkat_golongan' => filled($row['pangkat_golongan'] ?? null) ? trim((string) $row['pangkat_golongan']) : null,
            ]);

        $jumlah = DB::transaction(fn (): int => $this->simpanBaris($sekolahId, $baris));

        return back()->with('success', "{$jumlah} data pegawai berhasil disimpan");
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $sekolahId = $this->resolveSekolahIdWajib($request);

        $rows = $this->barisImpor($request->file('file'));

        if ($rows->count() > 1000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 1000 baris data per impor.');
        }

        $baris = $rows
            ->map(fn (Collection $row): array => [
                'nama_lengkap' => NormalisasiTeks::namaPegawai((string) ($row[0] ?? '')),
                'nip' => NormalisasiTeks::nip((string) ($row[1] ?? '')),
                'pangkat_golongan' => trim((string) ($row[2] ?? '')) ?: null,
            ])
            ->filter(fn (array $row): bool => $row['nama_lengkap'] !== '')
            ->values();

        if ($baris->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        $dilewati = 0;

        DB::transaction(function () use ($sekolahId, $baris, &$dilewati): void {
            foreach ($baris as $row) {
                if ($this->sudahAda($sekolahId, $row['nama_lengkap'], $row['nip'])) {
                    $dilewati++;

                    continue;
                }

                Pegawai::create($row + ['sekolah_id' => $sekolahId]);
            }
        });

        $masuk = $baris->count() - $dilewati;

        if ($masuk === 0) {
            return back()->with('error', 'Gagal Import: Semua data pegawai dalam file Excel sudah pernah diinputkan!');
        }

        $pesan = "{$masuk} data pegawai berhasil diimport";

        return back()->with('success', $dilewati > 0 ? "{$pesan} ({$dilewati} dilewati karena sudah ada)" : $pesan);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new TemplateExport(
                ['Nama Lengkap', 'NIP', 'Pangkat/Golongan'],
                collect([['Budi Santoso', '198001012005011001', 'III/a']]),
            ),
            'template-import-pegawai.xlsx',
        );
    }

    public function destroySemua(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        // Permission sudah dijaga middleware `kelola-pegawai-bm` di route.
        // Soft delete: histori mutasi dan unit lama tetap merujuk ID ini.
        Pegawai::where('sekolah_id', $sekolahId)->get()->each->delete();

        return back()->with('success', 'Seluruh data pegawai berhasil dihapus');
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
     * @param  Collection<int, array{nama_lengkap: string, nip: ?string, pangkat_golongan: ?string}>  $baris
     */
    private function simpanBaris(string $sekolahId, Collection $baris): int
    {
        $namaTerpakai = [];

        foreach ($baris as $row) {
            $kunci = mb_strtolower($row['nama_lengkap']);

            if (in_array($kunci, $namaTerpakai, true) || $this->sudahAda($sekolahId, $row['nama_lengkap'], $row['nip'])) {
                throw ValidationException::withMessages([
                    'baris' => "Data gagal disimpan! Pegawai/NIP '{$row['nama_lengkap']}' sudah pernah diinputkan.",
                ]);
            }

            $namaTerpakai[] = $kunci;
            Pegawai::create($row + ['sekolah_id' => $sekolahId]);
        }

        return $baris->count();
    }

    private function sudahAda(string $sekolahId, string $nama, ?string $nip): bool
    {
        $nama = NormalisasiTeks::namaPegawai($nama);
        $nip = NormalisasiTeks::nip($nip);

        return Pegawai::where('sekolah_id', $sekolahId)
            ->where(fn ($q) => $q
                ->whereRaw('lower(nama_lengkap) = ?', [mb_strtolower(trim($nama))])
                ->when(filled($nip), fn ($q) => $q->orWhere('nip', $nip)))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'nip' => ['nullable', 'string', 'max:30'],
            'pangkat_golongan' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['nama_lengkap'] = NormalisasiTeks::namaPegawai($validated['nama_lengkap']);
        $validated['nip'] = NormalisasiTeks::nip($validated['nip'] ?? null);

        return $validated;
    }
}
