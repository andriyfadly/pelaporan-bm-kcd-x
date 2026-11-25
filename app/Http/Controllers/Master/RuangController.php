<?php

namespace App\Http\Controllers\Master;

use App\Exports\TemplateExport;
use App\Http\Controllers\Concerns\ImportsBaris;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Ruang;
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

class RuangController extends Controller
{
    use ImportsBaris, ResolvesSekolah, TenantScope;

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

    /** Tambah banyak baris sekaligus (acuan: data_ruang.php). */
    public function storeMassal(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $validated = $request->validate([
            'baris' => ['required', 'array', 'min:1', 'max:100'],
            'baris.*.nama_ruang' => ['required', 'string', 'max:150'],
            'baris.*.kode' => ['nullable', 'string', 'max:50'],
        ]);

        $jumlah = DB::transaction(function () use ($validated, $sekolahId): int {
            $namaTerpakai = [];

            foreach ($validated['baris'] as $row) {
                $nama = trim($row['nama_ruang']);
                $kunci = mb_strtolower($nama);

                if (in_array($kunci, $namaTerpakai, true) || $this->namaTerpakai($sekolahId, $nama)) {
                    throw ValidationException::withMessages([
                        'baris' => "Data gagal disimpan! Nama ruangan '{$nama}' sudah pernah diinputkan.",
                    ]);
                }

                $namaTerpakai[] = $kunci;
                Ruang::create([
                    'sekolah_id' => $sekolahId,
                    'nama_ruang' => $nama,
                    'kode' => filled($row['kode'] ?? null) ? trim((string) $row['kode']) : null,
                ]);
            }

            return count($validated['baris']);
        });

        return back()->with('success', "{$jumlah} data ruang berhasil ditambahkan");
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
                'nama_ruang' => trim((string) ($row[0] ?? '')),
                'kode' => trim((string) ($row[1] ?? '')) ?: null,
            ])
            ->filter(fn (array $row): bool => $row['nama_ruang'] !== '')
            ->values();

        if ($baris->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        $dilewati = 0;

        DB::transaction(function () use ($sekolahId, $baris, &$dilewati): void {
            foreach ($baris as $row) {
                if ($this->namaTerpakai($sekolahId, $row['nama_ruang'])) {
                    $dilewati++;

                    continue;
                }

                Ruang::create($row + ['sekolah_id' => $sekolahId]);
            }
        });

        $masuk = $baris->count() - $dilewati;

        if ($masuk === 0) {
            return back()->with('error', 'Gagal Import: Semua data ruang dalam file Excel sudah pernah diinputkan!');
        }

        $pesan = "{$masuk} data ruang berhasil diimport";

        return back()->with('success', $dilewati > 0 ? "{$pesan} ({$dilewati} dilewati karena sudah ada)" : $pesan);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new TemplateExport(
                ['Nama Ruangan', 'Kode'],
                collect([['Ruang Guru', ''], ['Ruang Tata Usaha', '']]),
            ),
            'template-import-ruang.xlsx',
        );
    }

    public function destroySemua(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        // Soft delete; FK `nullOnDelete` melepas penempatan unit tanpa
        // menghapus histori mutasi.
        Ruang::where('sekolah_id', $sekolahId)->get()->each->delete();

        return back()->with('success', 'Seluruh data ruang berhasil dihapus');
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

        if ($this->namaTerpakai($sekolahId, $validated['nama_ruang'], $ignoreId)) {
            throw ValidationException::withMessages([
                'nama_ruang' => 'Nama ruang sudah dipakai di sekolah ini.',
            ]);
        }

        return $validated + ['sekolah_id' => $sekolahId];
    }

    private function namaTerpakai(string $sekolahId, string $nama, ?string $ignoreId = null): bool
    {
        return Ruang::where('sekolah_id', $sekolahId)
            ->whereRaw('lower(nama_ruang) = ?', [mb_strtolower(trim($nama))])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
