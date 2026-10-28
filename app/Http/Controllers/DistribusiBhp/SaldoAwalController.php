<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpSaldoAwalTemplateExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Services\DistribusiBhp\SaldoAwalService;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Flow saldo awal terpisah dari penerimaan biasa — mengikuti pola
 * sistem-manajemen-aset: halaman sendiri, filter periode + sumber dana,
 * kartu statistik, dan tabel per barang.
 */
class SaldoAwalController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly SaldoAwalService $saldoAwal) {}

    public function index(Request $request): Response
    {
        // Tidak ada pool pusat: saldo awal selalu terikat sekolah, jadi halaman
        // ini butuh konteks sekolah (query string sekolah_id untuk admin).
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            'periode' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $sumberDana = SumberDana::normalisasi(
            $validated['sumber_dana'] ?? SumberDana::aktif() ?? SumberDana::BOS,
        );
        $periode = isset($validated['periode']) ? (int) $validated['periode'] : (int) now()->year;

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $sumberDana, $periode): Response {
            $details = PenerimaanDetail::query()
                ->whereHas('penerimaan', fn ($q) => $q
                    ->where('is_saldo_awal', true)
                    ->where('periode_tahun', $periode)
                    ->where('sekolah_id', $sekolahId))
                ->with(['barang', 'penerimaan'])
                ->get();

            return Inertia::render('DistribusiBhp/SaldoAwal/Index', [
                'sumberDana' => $sumberDana,
                'periode' => $periode,
                'items' => $details->map(fn (PenerimaanDetail $d): array => [
                    'id' => $d->id,
                    'penerimaan_id' => $d->bhp_penerimaan_id,
                    'barang_id' => $d->barang_id,
                    'nama_barang' => $d->barang?->nama_barang,
                    'kode_barang' => $d->barang?->kode_barang,
                    'satuan' => $d->barang?->satuan,
                    'jumlah' => $d->jumlah,
                    'harga_satuan' => $d->harga_satuan,
                    'nilai_total' => $d->nilai_total,
                ])->values(),
                'stats' => [
                    'total_barang' => PenerimaanDetail::query()
                        ->whereHas('penerimaan', fn ($q) => $q
                            ->where('is_saldo_awal', true)
                            ->where('periode_tahun', $periode)
                            ->where('sekolah_id', $sekolahId))
                        ->distinct()
                        ->count('barang_id'),
                    'total_unit' => (int) PenerimaanDetail::query()
                        ->whereHas('penerimaan', fn ($q) => $q
                            ->where('is_saldo_awal', true)
                            ->where('periode_tahun', $periode)
                            ->where('sekolah_id', $sekolahId))
                        ->sum('jumlah'),
                    'total_nilai' => (int) PenerimaanDetail::query()
                        ->whereHas('penerimaan', fn ($q) => $q
                            ->where('is_saldo_awal', true)
                            ->where('periode_tahun', $periode)
                            ->where('sekolah_id', $sekolahId))
                        ->sum('nilai_total'),
                ],
                'availablePeriods' => Penerimaan::query()
                    ->where('is_saldo_awal', true)
                    ->where('sekolah_id', $sekolahId)
                    ->whereNotNull('periode_tahun')
                    ->distinct()
                    ->orderByDesc('periode_tahun')
                    ->pluck('periode_tahun')
                    ->values(),
                'barang' => Barang::orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan']),
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        // Tidak ada pool pusat: saldo awal wajib milik satu sekolah.
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $sumberDanaValidasi = SumberDana::normalisasi((string) $request->input('sumber_dana'));

        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'periode_tahun' => ['required', 'integer', 'between:2000,2100'],
            'details' => 'required|array|min:1',
            'details.*.barang_id' => ['required', 'uuid', SumberDana::existsRule('barang', $sumberDanaValidasi)],
            'details.*.jumlah' => 'required|integer|min:1',
            'details.*.harga_satuan' => 'required|integer|min:0',
            'details.*.merek_type' => ['nullable', 'string', 'max:255'],
            'details.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        SumberDana::dengan($sumberDana, function () use ($validated, $sekolahId, $request, $sumberDana) {
            $barangIds = collect($validated['details'])->pluck('barang_id')->all();
            $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

            foreach ($validated['details'] as $index => $detail) {
                if (! $barangs->has($detail['barang_id'])) {
                    throw ValidationException::withMessages([
                        "details.{$index}.barang_id" => 'Barang '.$detail['barang_id'].' tidak sesuai dengan sumber dana '.$sumberDana.'.',
                    ]);
                }
            }

            $details = collect($validated['details'])
                ->map(fn (array $d): array => [
                    'barang_id' => $d['barang_id'],
                    'jumlah' => (int) $d['jumlah'],
                    'harga_satuan' => (int) $d['harga_satuan'],
                    'merek_type' => $d['merek_type'] ?? null,
                    'keterangan' => $d['keterangan'] ?? null,
                ])
                ->all();

            $this->saldoAwal->create($validated, $sekolahId, $request->user(), $details, $barangs->all());
        });

        return redirect()
            ->route('distribusi-bhp.'.strtolower($sumberDana).'.saldo-awal.index', [
                'periode' => $validated['periode_tahun'],
            ])
            ->with('success', 'Saldo awal berhasil disimpan.');
    }

    public function destroy(Request $request, PenerimaanDetail $detail): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $penerimaan = $detail->penerimaan;

        if (! $penerimaan || ! $penerimaan->is_saldo_awal) {
            abort(404);
        }

        if ((string) $penerimaan->sekolah_id !== $sekolahId) {
            abort(403, 'Akses ditolak: data ini milik sekolah lain.');
        }

        try {
            $this->saldoAwal->deleteDetail($detail, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Barang saldo awal berhasil dihapus.');
    }

    /**
     * Impor saldo awal dari Excel (all-or-nothing: satu baris invalid = batal semua).
     *
     * Kolom (index berbasis 0): [0] Kode Barang*, [1] Jumlah*, [2] Harga Satuan*,
     * [3] Merek/Tipe, [4] Keterangan. Baris 1 = header, dilewati.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls',
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'periode_tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);

        // Tidak ada pool pusat: import saldo awal wajib milik satu sekolah.
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);
        $periode = (int) $validated['periode_tahun'];
        SumberDana::setAktif($sumberDana);

        try {
            $rows = Excel::toCollection(null, $request->file('file'))->first();
        } catch (Throwable) {
            return back()->with('error', 'Berkas tidak dapat dibaca. Pastikan format .xlsx atau .xls yang valid.');
        }

        $dataRows = $rows instanceof Collection ? $rows->slice(1)->values() : collect();

        // ponytail: batas 2000 baris per import, naikkan jika kebutuhan riil melebihi
        if ($dataRows->count() > 2000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 2000 baris data per impor.');
        }

        // Buang baris yang benar-benar kosong (trailing blank row umum di Excel).
        $dataRows = $dataRows
            ->filter(fn ($row): bool => collect($row)->contains(fn ($v): bool => trim((string) $v) !== ''))
            ->values();

        if ($dataRows->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        // Pre-validasi semua baris sebelum menyentuh DB (all-or-nothing).
        $errors = [];
        $kodeList = $dataRows->pluck(0)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        $barangPerKode = Barang::whereIn('kode_barang', $kodeList)
            ->get()
            ->keyBy(fn (Barang $b): string => (string) $b->kode_barang);

        // Set barang yang sudah punya saldo awal periode ini, diambil SEKALI
        // (bukan query per baris berkas).
        $sudahAdaSet = $this->saldoAwal->barangSudahAda($sekolahId, $sumberDana, $periode);

        $terlihat = [];
        $hargaPerBaris = [];
        foreach ($dataRows as $i => $row) {
            $noBaris = $i + 2;
            // Nilai mentah (bisa float/int dari sel numerik Excel) — jangan
            // di-cast ke string sebelum validasi angka, agar 1.5 tetap terlihat
            // sebagai desimal, bukan "15".
            $raw = fn (int $idx): mixed => $row[$idx] ?? null;
            $get = fn (int $idx): string => trim((string) ($raw($idx) ?? ''));

            $kode = $get(0);
            $barang = $kode !== '' ? $barangPerKode->get($kode) : null;

            if ($kode === '') {
                $errors[] = "Baris {$noBaris}: Kode Barang wajib diisi.";

                continue;
            }

            if (! $barang) {
                $errors[] = "Baris {$noBaris}: Kode Barang \"{$kode}\" tidak ditemukan di master barang.";

                continue;
            }

            if (isset($terlihat[$barang->id])) {
                $errors[] = "Baris {$noBaris}: Barang \"{$barang->nama_barang}\" muncul lebih dari satu kali di berkas.";

                continue;
            }
            $terlihat[$barang->id] = true;

            if (isset($sudahAdaSet[$barang->id])) {
                $errors[] = "Baris {$noBaris}: Saldo awal \"{$barang->nama_barang}\" periode {$periode} sudah ada.";
            }

            if (! $this->angkaBulat($raw(1))) {
                $errors[] = "Baris {$noBaris}: Jumlah harus berupa bilangan bulat.";
            } elseif ((int) $this->normalisasiAngka($raw(1)) <= 0) {
                $errors[] = "Baris {$noBaris}: Jumlah harus lebih dari 0.";
            }

            if (! $this->angkaBulat($raw(2))) {
                $errors[] = "Baris {$noBaris}: Harga Satuan wajib berupa bilangan bulat (tanpa desimal).";
            } elseif ((int) $this->normalisasiAngka($raw(2)) < 0) {
                $errors[] = "Baris {$noBaris}: Harga Satuan tidak boleh negatif.";
            }

            // Nilai total dihitung dari angka yang sudah dinormalisasi agar tidak
            // ada selisih antara yang divalidasi dan yang disimpan.
            $hargaPerBaris[$i] = (int) $this->normalisasiAngka($raw(2));
        }

        if ($errors !== []) {
            $ringkasan = count($errors) > 5
                ? implode(' ', array_slice($errors, 0, 5)).' (dan '.(count($errors) - 5).' kesalahan lain)'
                : implode(' ', $errors);

            return back()->with('error', "Impor dibatalkan, tidak ada data tersimpan. {$ringkasan}");
        }

        $details = [];
        $barangs = [];
        foreach ($dataRows as $i => $row) {
            $get = fn (int $idx): string => trim((string) ($row[$idx] ?? ''));
            /** @var Barang $barang */
            $barang = $barangPerKode->get($get(0));

            $barangs[$barang->id] = $barang;
            $details[] = [
                'barang_id' => $barang->id,
                'jumlah' => (int) $this->normalisasiAngka($row[1] ?? null),
                'harga_satuan' => $hargaPerBaris[$i],
                'merek_type' => $get(3) ?: null,
                'keterangan' => $get(4) ?: null,
            ];
        }

        try {
            $this->saldoAwal->create(
                ['sumber_dana' => $sumberDana, 'periode_tahun' => $periode],
                $sekolahId,
                $request->user(),
                $details,
                $barangs,
            );
        } catch (ValidationException $e) {
            return back()->with('error', 'Impor dibatalkan, tidak ada data tersimpan. '.collect($e->errors())->flatten()->first());
        } catch (QueryException $e) {
            // Partial unique index saldo awal menangkap race concurrent:
            // pemenang insert sukses, yang kalah dapat violation.
            if ($e->getCode() === '23505') {
                return back()->with('error', 'Impor dibatalkan: saldo awal untuk periode '.$periode.' sudah ada (kemungkinan dibuat oleh proses lain).');
            }

            throw $e;
        }

        return redirect()
            ->route('distribusi-bhp.'.strtolower($sumberDana).'.saldo-awal.index', ['periode' => $periode])
            ->with('success', count($details).' barang saldo awal berhasil diimpor.');
    }

    public function template(Request $request): BinaryFileResponse
    {
        // Whitelist konsisten dengan index/store/import — nilai asing ditolak,
        // bukan diam-diam menghasilkan template kosong.
        // Whitelist konsisten dengan index/store/import — nilai asing ditolak,
        // bukan diam-diam menghasilkan template kosong.
        $diminta = (string) $request->query('sumber_dana', SumberDana::BOS);
        if (! SumberDana::valid($diminta)) {
            abort(422, 'Sumber dana tidak valid.');
        }
        $sumberDana = SumberDana::normalisasi($diminta);

        $contoh = SumberDana::dengan($sumberDana, fn () => Barang::orderBy('nama_barang')
            ->limit(3)
            ->get()
            ->map(fn (Barang $b): array => [
                'kode_barang' => (string) $b->kode_barang,
                'jumlah' => 0,
                'harga_satuan' => 0,
                'merek_type' => '',
                'keterangan' => '',
            ])
            ->all());

        return Excel::download(
            new BhpSaldoAwalTemplateExport($contoh),
            "template-saldo-awal-{$sumberDana}.xlsx",
        );
    }

    /**
     * Buang pemisah ribuan (spasi/titik/koma) sehingga "1.500.000", "1 500 000",
     * dan "1500000" diperlakukan sama. Koma juga dipakai sebagai pemisah ribuan
     * gaya Indonesia, jadi tidak diperlakukan sebagai desimal.
     */
    private function normalisasiAngka(mixed $nilai): string
    {
        return str_replace(['.', ',', ' '], '', (string) $nilai);
    }

    /**
     * Benar hanya bila nilai murni bilangan bulat: menolak float non-bulat
     * (1.5), desimal, notasi ilmiah, tanda minus, dan teks.
     *
     * Sel Excel numerik sampai sebagai float/int, sel teks sebagai string —
     * keduanya harus melewati aturan yang sama.
     */
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

        return preg_match('/^\d+$/', $this->normalisasiAngka($teks)) === 1;
    }
}
