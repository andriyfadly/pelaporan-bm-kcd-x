<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpSaldoAwalTemplateExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Services\DistribusiBhp\SaldoAwalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $sekolahId = $this->resolveSekolahId($request);
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
            'periode' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $sumberDana = strtoupper((string) ($validated['sumber_dana'] ?? 'BOS'));
        $periode = isset($validated['periode']) ? (int) $validated['periode'] : (int) now()->year;

        $details = PenerimaanDetail::query()
            ->whereHas('penerimaan', fn ($q) => $q
                ->where('is_saldo_awal', true)
                ->where('sumber_dana', $sumberDana)
                ->where('periode_tahun', $periode)
                ->when($sekolahId !== null, fn ($w) => $w->where('sekolah_id', $sekolahId)))
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
                'total_barang' => $details->count(),
                'total_unit' => (int) $details->sum('jumlah'),
                'total_nilai' => (int) $details->sum('nilai_total'),
            ],
            'availablePeriods' => Penerimaan::query()
                ->where('is_saldo_awal', true)
                ->where('sumber_dana', $sumberDana)
                ->when($sekolahId !== null, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->whereNotNull('periode_tahun')
                ->distinct()
                ->orderByDesc('periode_tahun')
                ->pluck('periode_tahun')
                ->values(),
            'barang' => Barang::where('sumber_dana', $sumberDana)
                ->orderBy('nama_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'periode_tahun' => ['required', 'integer', 'between:2000,2100'],
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|uuid|exists:bhp_barang,id',
            'details.*.jumlah' => 'required|integer|min:1',
            'details.*.harga_satuan' => 'required|integer|min:0',
            'details.*.merek_type' => ['nullable', 'string', 'max:255'],
            'details.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $barangIds = collect($validated['details'])->pluck('barang_id')->all();
        $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

        foreach ($validated['details'] as $index => $detail) {
            $barang = $barangs->get($detail['barang_id']);
            if (! $barang) {
                throw ValidationException::withMessages([
                    "details.{$index}.barang_id" => 'Barang tidak ditemukan.',
                ]);
            }

            if ($barang->sumber_dana !== $validated['sumber_dana']) {
                throw ValidationException::withMessages([
                    "details.{$index}.barang_id" => 'Barang '.$barang->nama_barang.' tidak sesuai dengan sumber dana '.$validated['sumber_dana'].'.',
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

        $this->saldoAwal->create($validated, (string) $sekolahId, $request->user(), $details, $barangs->all());

        return redirect()
            ->route('distribusi-bhp.saldo-awal.index', [
                'sumber_dana' => $validated['sumber_dana'],
                'periode' => $validated['periode_tahun'],
            ])
            ->with('success', 'Saldo awal berhasil disimpan.');
    }

    public function destroy(Request $request, PenerimaanDetail $detail): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        $penerimaan = $detail->penerimaan;

        if (! $penerimaan || ! $penerimaan->is_saldo_awal) {
            abort(404);
        }

        if ($sekolahId !== null && (string) $penerimaan->sekolah_id !== (string) $sekolahId) {
            abort(403, 'Akses ditolak: data ini milik sekolah lain.');
        }

        $this->saldoAwal->delete($penerimaan, $request->user());

        return back()->with('success', 'Barang saldo awal berhasil dihapus.');
    }

    /**
     * Impor saldo awal dari Excel (all-or-nothing: satu baris invalid = batal semua).
     *
     * Kolom (index berbasis 0): [0] Kode Barang*, [1] Jumlah*, [2] Harga Satuan*,
     * [3] Merek/Tipe, [4] Keterangan.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls',
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'periode_tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $sekolahId = (string) $this->resolveSekolahId($request);
        $sumberDana = $validated['sumber_dana'];
        $periode = (int) $validated['periode_tahun'];

        $rows = Excel::toCollection(null, $request->file('file'))->first();
        $dataRows = $rows instanceof Collection ? $rows->slice(1)->values() : collect();

        // ponytail: batas 2000 baris per import, naikkan jika kebutuhan riil melebihi
        if ($dataRows->count() > 2000) {
            return back()->with('error', 'Berkas terlalu besar: maksimal 2000 baris data per impor.');
        }

        if ($dataRows->isEmpty()) {
            return back()->with('error', 'Berkas tidak berisi data untuk diimpor.');
        }

        // Pre-validasi semua baris sebelum menyentuh DB (all-or-nothing).
        $errors = [];
        $kodeList = $dataRows->pluck(0)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        $barangPerKode = Barang::whereIn('kode_barang', $kodeList)
            ->get()
            ->keyBy(fn (Barang $b): string => (string) $b->kode_barang);

        $terlihat = [];
        foreach ($dataRows as $i => $row) {
            $noBaris = $i + 2;
            $get = fn (int $idx): string => trim((string) ($row[$idx] ?? ''));

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

            if ((string) $barang->sumber_dana !== $sumberDana) {
                $errors[] = "Baris {$noBaris}: Barang \"{$barang->nama_barang}\" bukan sumber dana {$sumberDana}.";

                continue;
            }

            if (isset($terlihat[$barang->id])) {
                $errors[] = "Baris {$noBaris}: Barang \"{$barang->nama_barang}\" muncul lebih dari satu kali di berkas.";

                continue;
            }
            $terlihat[$barang->id] = true;

            if (app(SaldoAwalService::class)->sudahAdaUntuk($barang->id, $sekolahId, $sumberDana, $periode)) {
                $errors[] = "Baris {$noBaris}: Saldo awal \"{$barang->nama_barang}\" periode {$periode} sudah ada.";
            }

            if ((int) $get(1) <= 0) {
                $errors[] = "Baris {$noBaris}: Jumlah harus lebih dari 0.";
            }

            if ($get(2) === '' || (int) str_replace([',', ' ', '.'], '', $get(2)) < 0) {
                $errors[] = "Baris {$noBaris}: Harga Satuan wajib diisi dan tidak boleh negatif.";
            }
        }

        if ($errors !== []) {
            $ringkasan = count($errors) > 5
                ? implode(' ', array_slice($errors, 0, 5)).' (dan '.(count($errors) - 5).' kesalahan lain)'
                : implode(' ', $errors);

            return back()->with('error', "Impor dibatalkan, tidak ada data tersimpan. {$ringkasan}");
        }

        $details = [];
        $barangs = [];
        foreach ($dataRows as $row) {
            $get = fn (int $idx): string => trim((string) ($row[$idx] ?? ''));
            /** @var Barang $barang */
            $barang = $barangPerKode->get($get(0));

            $barangs[$barang->id] = $barang;
            $details[] = [
                'barang_id' => $barang->id,
                'jumlah' => (int) $get(1),
                'harga_satuan' => (int) str_replace([',', ' ', '.'], '', $get(2)),
                'merek_type' => $get(3) ?: null,
                'keterangan' => $get(4) ?: null,
            ];
        }

        $this->saldoAwal->create(
            ['sumber_dana' => $sumberDana, 'periode_tahun' => $periode],
            $sekolahId,
            $request->user(),
            $details,
            $barangs,
        );

        return redirect()
            ->route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => $sumberDana, 'periode' => $periode])
            ->with('success', count($details).' barang saldo awal berhasil diimpor.');
    }

    public function template(Request $request): BinaryFileResponse
    {
        $sumberDana = strtoupper((string) $request->query('sumber_dana', 'BOS'));

        $contoh = Barang::where('sumber_dana', $sumberDana)
            ->orderBy('nama_barang')
            ->limit(3)
            ->get()
            ->map(fn (Barang $b): array => [
                'kode_barang' => (string) $b->kode_barang,
                'jumlah' => 0,
                'harga_satuan' => 0,
                'merek_type' => '',
                'keterangan' => '',
            ])
            ->all();

        return Excel::download(
            new BhpSaldoAwalTemplateExport($contoh),
            "template-saldo-awal-{$sumberDana}.xlsx",
        );
    }
}
