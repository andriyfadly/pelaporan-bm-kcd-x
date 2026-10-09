<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpBarangTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BarangController extends Controller
{
    private const KODE_BARANG_OPTIONS = [
        ['kode' => '1.1.7.01.01', 'uraian' => 'Bahan'],
        ['kode' => '1.1.7.01.02', 'uraian' => 'Suku Cadang'],
        ['kode' => '1.1.7.01.03', 'uraian' => 'Alat/Bahan Untuk Kegiatan Kantor'],
        ['kode' => '1.1.7.01.04', 'uraian' => 'Obat-Obatan'],
        ['kode' => '1.1.7.01.07', 'uraian' => 'Naturan dan Pakan'],
    ];

    public function index(Request $request): Response
    {
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            $items = Barang::with(['kodeRekening', 'harga'])
                ->orderBy('nama_barang')
                ->get();

            $stats = [
                'total' => $items->count(),
                'stok_terpakai' => $items->filter(fn (Barang $b) => $b->harga->isNotEmpty())->count(),
                'bulan_ini' => Barang::where('created_at', '>=', now()->startOfMonth())->count(),
            ];

            return Inertia::render('DistribusiBhp/Master/Barang/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'stats' => $stats,
                'kodeBarangOptions' => static::KODE_BARANG_OPTIONS,
                'kodeRekening' => KodeRekening::orderBy('kode')->get(['id', 'kode', 'nama', 'kode_barang', 'uraian_kode_barang']),
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'spesifikasi_barang_nusp' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'kode_rekening_id' => ['nullable', 'uuid'],
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'harga_satuan' => ['required', 'integer', 'min:0'],
        ]);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        return SumberDana::dengan($sumberDana, function () use ($validated, $request, $sumberDana): RedirectResponse {
            if (! empty($validated['kode_rekening_id'])
                && ! KodeRekening::whereKey($validated['kode_rekening_id'])->exists()) {
                return back()->withErrors([
                    'kode_rekening_id' => 'Kode rekening tidak sesuai dengan sumber dana '.$sumberDana.'.',
                ])->withInput();
            }

            DB::transaction(function () use ($validated, $request) {
                $barang = Barang::create([
                    'kode_barang' => $validated['kode_barang'] ?? null,
                    'nama_barang' => $validated['nama_barang'],
                    'spesifikasi_barang_nusp' => $validated['spesifikasi_barang_nusp'] ?? null,
                    'satuan' => $validated['satuan'] ?? null,
                    'kode_rekening_id' => $validated['kode_rekening_id'] ?? null,
                ]);

                $barang->harga()->create([
                    'harga_satuan' => $validated['harga_satuan'],
                    'created_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'Barang berhasil ditambahkan.');
        });
    }

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'spesifikasi_barang_nusp' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'kode_rekening_id' => ['nullable', 'uuid'],
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
        ]);

        if (! empty($validated['kode_rekening_id'])
            && ! KodeRekening::whereKey($validated['kode_rekening_id'])->exists()) {
            return back()->withErrors([
                'kode_rekening_id' => 'Kode rekening tidak sesuai dengan sumber dana '.$barang->sumber_dana.'.',
            ])->withInput();
        }

        $barang->update([
            'kode_barang' => $validated['kode_barang'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'spesifikasi_barang_nusp' => $validated['spesifikasi_barang_nusp'] ?? null,
            'satuan' => $validated['satuan'] ?? null,
            'kode_rekening_id' => $validated['kode_rekening_id'] ?? null,
        ]);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $dipakai = PenerimaanDetail::where('barang_id', $barang->id)->exists()
            || PengajuanDetail::where('barang_id', $barang->id)->exists();

        if ($dipakai) {
            return back()->with('error', 'Barang tidak dapat dihapus karena masih dipakai pada penerimaan/pengajuan.');
        }

        DB::transaction(function () use ($barang) {
            $barang->harga()->delete();
            $barang->delete();
        });

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    public function hargaIndex(Barang $barang): Response
    {
        return Inertia::render('DistribusiBhp/Master/Barang/KelolaHarga', [
            'barang' => $barang->only(['id', 'kode_barang', 'nama_barang', 'spesifikasi_barang_nusp', 'satuan']),
            'sumberDana' => $barang->sumber_dana,
            'hargaList' => $barang->harga()->orderByDesc('created_at')->get(['id', 'harga_satuan', 'created_at']),
        ]);
    }

    public function storeHarga(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'harga_satuan' => ['required', 'integer', 'min:1'],
        ]);

        $barang->harga()->create([
            'harga_satuan' => $validated['harga_satuan'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Harga barang berhasil ditambahkan.');
    }

    public function updateHarga(Request $request, HargaBarang $harga): RedirectResponse
    {
        $validated = $request->validate([
            'harga_satuan' => ['required', 'integer', 'min:1'],
        ]);

        $harga->update(['harga_satuan' => $validated['harga_satuan']]);

        return back()->with('success', 'Harga barang berhasil diperbarui.');
    }

    public function destroyHarga(HargaBarang $harga): RedirectResponse
    {
        $harga->delete();

        return back()->with('success', 'Harga barang berhasil dihapus.');
    }

    /**
     * Impor data barang dari Excel (all-or-nothing: satu baris invalid = batal semua).
     *
     * Kolom (index berbasis 0): [0] Kode Rekening*, [1] Nama Barang*, [2] Spesifikasi/NUSP,
     * [3] Satuan*, [4] Harga Satuan*. Baris 1 = header, dilewati.
     *
     * Kode Barang & uraian diambil otomatis dari kode rekening. Untuk nama barang
     * yang sama (dalam sekolah & sumber dana ini), harga baru ditambahkan ke daftar
     * harga barang yang sudah ada.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls',
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
        ]);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        return SumberDana::dengan($sumberDana, function () use ($request): RedirectResponse {
            try {
                $rows = Excel::toCollection(null, $request->file('file'))->first();
            } catch (Throwable) {
                return back()->with('error', 'Berkas tidak dapat dibaca. Pastikan format .xlsx atau .xls yang valid.');
            }

            $dataRows = $rows instanceof Collection ? $rows->slice(1)->values() : collect();

            // ponytail: batas 1000 baris per import, naikkan jika kebutuhan riil melebihi
            if ($dataRows->count() > 1000) {
                return back()->with('error', 'Berkas terlalu besar: maksimal 1000 baris data per impor.');
            }

            $dataRows = $dataRows
                ->filter(fn ($row): bool => collect($row)->contains(fn ($v): bool => trim((string) $v) !== ''))
                ->values();

            if ($dataRows->isEmpty()) {
                return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
            }

            $errors = [];
            $rekeningPerKode = KodeRekening::whereIn('kode', $dataRows->pluck(0)->map(fn ($v) => trim((string) $v))->filter()->unique())
                ->get()
                ->keyBy(fn (KodeRekening $r): string => (string) $r->kode);

            // Barang yang sudah ada, di-index per (nama lowercase) → instance.
            $barangPerNama = Barang::all()
                ->keyBy(fn (Barang $b): string => mb_strtolower(trim($b->nama_barang)));

            $hargaPerBaris = [];
            foreach ($dataRows as $i => $row) {
                $noBaris = $i + 2;
                $raw = fn (int $idx): mixed => $row[$idx] ?? null;
                $get = fn (int $idx): string => trim((string) ($raw($idx) ?? ''));

                $kodeRekening = $get(0);
                if ($kodeRekening === '') {
                    $errors[] = "Baris {$noBaris}: Kode Rekening wajib diisi.";
                } elseif (! $rekeningPerKode->has($kodeRekening)) {
                    $errors[] = "Baris {$noBaris}: Kode Rekening \"{$kodeRekening}\" tidak ditemukan.";
                }

                if ($get(1) === '') {
                    $errors[] = "Baris {$noBaris}: Nama Barang wajib diisi.";
                }

                if ($get(3) === '') {
                    $errors[] = "Baris {$noBaris}: Satuan wajib diisi.";
                }

                if (! $this->angkaBulat($raw(4))) {
                    $errors[] = "Baris {$noBaris}: Harga Satuan wajib berupa bilangan bulat.";
                } elseif ((int) $this->normalisasiAngka($raw(4)) <= 0) {
                    $errors[] = "Baris {$noBaris}: Harga Satuan harus lebih dari 0.";
                }

                $hargaPerBaris[$i] = (int) $this->normalisasiAngka($raw(4));
            }

            if ($errors !== []) {
                $ringkasan = count($errors) > 5
                    ? implode(' ', array_slice($errors, 0, 5)).' (dan '.(count($errors) - 5).' kesalahan lain)'
                    : implode(' ', $errors);

                return back()->with('error', "Impor dibatalkan, tidak ada data tersimpan. {$ringkasan}");
            }

            $dibuat = 0;
            $hargaDitambah = 0;

            try {
                DB::transaction(function () use ($dataRows, $hargaPerBaris, $rekeningPerKode, $barangPerNama, $request, &$dibuat, &$hargaDitambah) {
                    foreach ($dataRows as $i => $row) {
                        $get = fn (int $idx): string => trim((string) ($row[$idx] ?? ''));
                        /** @var KodeRekening $rekening */
                        $rekening = $rekeningPerKode->get($get(0));
                        $nama = $get(1);
                        $key = mb_strtolower($nama);

                        $barang = $barangPerNama->get($key);

                        if (! $barang) {
                            $barang = Barang::create([
                                'kode_barang' => $rekening->kode_barang,
                                'nama_barang' => $nama,
                                'spesifikasi_barang_nusp' => $get(2) ?: null,
                                'satuan' => $get(3),
                                'kode_rekening_id' => $rekening->id,
                            ]);
                            $barangPerNama->put($key, $barang);
                            $dibuat++;
                        }

                        $barang->harga()->create([
                            'harga_satuan' => $hargaPerBaris[$i],
                            'created_by' => $request->user()->id,
                        ]);
                        $hargaDitambah++;
                    }
                });
            } catch (QueryException $e) {
                return back()->with('error', 'Impor gagal: '.$e->getMessage());
            }

            return back()->with('success', "{$dibuat} barang baru diimpor, {$hargaDitambah} harga ditambahkan.");
        });
    }

    public function template(Request $request): BinaryFileResponse
    {
        $diminta = (string) $request->query('sumber_dana', SumberDana::BOS);
        if (! SumberDana::valid($diminta)) {
            abort(422, 'Sumber dana tidak valid.');
        }
        $sumberDana = SumberDana::normalisasi($diminta);

        $contoh = SumberDana::dengan($sumberDana, fn () => KodeRekening::orderBy('kode')
            ->limit(3)
            ->get()
            ->map(fn (KodeRekening $r): array => [
                'kode_rekening' => (string) $r->kode,
                'nama_barang' => '',
                'spesifikasi' => '',
                'satuan' => '',
                'harga_satuan' => 0,
            ])
            ->all());

        return Excel::download(
            new BhpBarangTemplateExport($contoh),
            "template-data-barang-{$sumberDana}.xlsx",
        );
    }

    private function normalisasiAngka(mixed $nilai): string
    {
        return str_replace(['.', ',', ' '], '', (string) $nilai);
    }

    private function angkaBulat(mixed $nilai): bool
    {
        if (is_int($nilai)) {
            return true;
        }

        if (is_float($nilai)) {
            return floor($nilai) === $nilai;
        }

        $teks = trim((string) $nilai);
        if ($teks === '') {
            return false;
        }

        return preg_match('/^\d+$/', str_replace(['.', ',', ' '], '', $teks)) === 1;
    }
}
