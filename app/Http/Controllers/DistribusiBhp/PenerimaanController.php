<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Penyedia;
use App\Services\DistribusiBhp\SaldoAwalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PenerimaanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(private readonly SaldoAwalService $saldoAwal) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
        ])['sumber_dana'] ?? 'BOS';
        $sumberDana = strtoupper((string) $sumberDana);

        $items = Penerimaan::with(['penyedia', 'details.barang'])
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('sumber_dana', $sumberDana)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('DistribusiBhp/Penerimaan/Index', [
            'items' => $items,
            'sumberDana' => $sumberDana,
            'barang' => Barang::where('sumber_dana', $sumberDana)->orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan']),
            'penyedia' => Penyedia::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'no_nota_invoice' => ['nullable', 'string', 'max:150'],
            'no_bast' => ['nullable', 'string', 'max:150'],
            'tanggal_bast' => ['nullable', 'date'],
            'no_kwitansi' => ['nullable', 'string', 'max:150'],
            'penyedia_id' => ['nullable', 'uuid', 'exists:bhp_penyedia,id'],
            'tanggal_titimangsa' => ['nullable', 'date'],
            'is_saldo_awal' => ['boolean'],
            'periode_tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|uuid|exists:bhp_barang,id',
            'details.*.jumlah' => 'required|integer|min:1',
            'details.*.harga_satuan' => 'required|integer|min:0',
            'details.*.merek_type' => ['nullable', 'string', 'max:255'],
            'details.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        // Kombinasi is_saldo_awal × periode_tahun harus konsisten:
        // saldo awal wajib menyebut periode, penerimaan biasa dilarang.
        if (($validated['is_saldo_awal'] ?? false) xor (bool) ($validated['periode_tahun'] ?? false)) {
            throw ValidationException::withMessages([
                'periode_tahun' => 'Periode tahun wajib diisi untuk saldo awal dan tidak boleh diisi untuk penerimaan biasa.',
            ]);
        }

        // Isolasi sumber dana sebelum transaksi. Barang wajib ada — exists
        // di validasi menjaminnya, tetapi guard ini tetap memutus bila tidak.
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
                'nilai_total' => (int) $d['jumlah'] * (int) $d['harga_satuan'],
                'merek_type' => $d['merek_type'] ?? null,
                'keterangan' => $d['keterangan'] ?? null,
            ])
            ->all();

        if ($validated['is_saldo_awal'] ?? false) {
            $this->saldoAwal->create($validated, (string) $sekolahId, $request->user(), $details, $barangs->all());
        } else {
            $this->simpanPenerimaanBiasa($validated, $sekolahId, $details);
        }

        return back()->with('success', 'Penerimaan barang berhasil disimpan.');
    }

    public function destroy(Request $request, Penerimaan $penerimaan): RedirectResponse
    {
        $this->authorizeSekolah($request, $penerimaan);

        $this->saldoAwal->delete($penerimaan, $request->user());

        return back()->with('success', 'Saldo awal berhasil dihapus.');
    }

    /**
     * Penerimaan biasa (bukan saldo awal): tanpa aturan uniqueness,
     * cukup header + detail dalam satu transaction.
     *
     * @param  array<string,mixed>  $validated
     * @param  array<int, array{barang_id:string,jumlah:int,harga_satuan:int,merek_type:?string,keterangan:?string}>  $details
     */
    private function simpanPenerimaanBiasa(array $validated, ?string $sekolahId, array $details): void
    {
        DB::transaction(function () use ($validated, $sekolahId, $details) {
            $penerimaan = Penerimaan::create([
                'sekolah_id' => $sekolahId,
                'no_nota_invoice' => $validated['no_nota_invoice'] ?? null,
                'no_bast' => $validated['no_bast'] ?? null,
                'tanggal_bast' => $validated['tanggal_bast'] ?? null,
                'no_kwitansi' => $validated['no_kwitansi'] ?? null,
                'penyedia_id' => $validated['penyedia_id'] ?? null,
                'tanggal_titimangsa' => $validated['tanggal_titimangsa'] ?? null,
                'is_saldo_awal' => false,
                'periode_tahun' => null,
                'sumber_dana' => $validated['sumber_dana'],
            ]);

            foreach ($details as $detail) {
                $penerimaan->details()->create($detail);
            }
        });
    }

    private function authorizeSekolah(Request $request, Penerimaan $penerimaan): void
    {
        $sekolahId = $this->resolveSekolahId($request);

        if ($sekolahId !== null && (string) $penerimaan->sekolah_id !== (string) $sekolahId) {
            abort(403, 'Akses ditolak: data ini milik sekolah lain.');
        }
    }
}
