<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Services\DistribusiBhp\SaldoAwalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
}
