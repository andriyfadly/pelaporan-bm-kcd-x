<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Penyedia;
use App\Services\DistribusiBhp\PenerimaanService;
use App\Services\DistribusiBhp\SaldoAwalService;
use App\Support\DistribusiBhp\SumberDana;
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

    public function __construct(
        private readonly SaldoAwalService $saldoAwal,
        private readonly PenerimaanService $penerimaan,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $sumberDana): Response {
            $items = Penerimaan::with(['penyedia', 'details.barang'])
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            return Inertia::render('DistribusiBhp/Penerimaan/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'barang' => Barang::orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan']),
                'penyedia' => Penyedia::orderBy('nama')->get(['id', 'nama']),
            ]);
        });
    }

    public function lunasBayar(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS));

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $sumberDana): Response {
            $items = Penerimaan::with(['penyedia', 'details.barang'])
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            return Inertia::render('DistribusiBhp/Penerimaan/LunasBayar', [
                'items' => $items,
                'sumberDana' => $sumberDana,
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        // Tidak ada pool pusat: penerimaan/saldo awal wajib milik satu sekolah.
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $sumberDana = SumberDana::normalisasi((string) $request->input('sumber_dana'));

        $isSaldoAwal = (bool) ($request->input('is_saldo_awal') ?? false);

        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'no_nota_invoice' => ['nullable', 'string', 'max:150'],
            'no_bast' => ['nullable', 'string', 'max:150'],
            'tanggal_bast' => ['nullable', 'date'],
            'no_kwitansi' => ['nullable', 'string', 'max:150'],
            'penyedia_id' => [$isSaldoAwal ? 'nullable' : 'required', 'uuid', 'exists:bhp_penyedia,id'],
            'tanggal_titimangsa' => ['nullable', 'date'],
            'is_saldo_awal' => ['boolean'],
            'periode_tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'details' => 'required|array|min:1',
            'details.*.barang_id' => ['required', 'uuid', 'distinct', SumberDana::existsRule('barang', $sumberDana)],
            'details.*.jumlah' => 'required|integer|min:1',
            'details.*.harga_satuan' => 'required|integer|min:0',
            'details.*.merek_type' => ['nullable', 'string', 'max:255'],
            'details.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'details.*.barang_id.distinct' => 'Satu barang tidak boleh muncul lebih dari sekali dalam satu penerimaan.',
        ]);

        // Kombinasi is_saldo_awal × periode_tahun harus konsisten:
        // saldo awal wajib menyebut periode, penerimaan biasa dilarang.
        if (($validated['is_saldo_awal'] ?? false) xor (bool) ($validated['periode_tahun'] ?? false)) {
            throw ValidationException::withMessages([
                'periode_tahun' => 'Periode tahun wajib diisi untuk saldo awal dan tidak boleh diisi untuk penerimaan biasa.',
            ]);
        }

        // Penerimaan biasa wajib punya dokumen sumber & tanggal BAST, karena
        // tanggal BAST menentukan bulan mutasi stok pada laporan.
        if (! ($validated['is_saldo_awal'] ?? false)) {
            $wajib = [
                'no_bast' => 'Nomor BAST wajib diisi untuk penerimaan barang.',
                'tanggal_bast' => 'Tanggal BAST wajib diisi dan menentukan bulan mutasi stok.',
            ];

            $errors = [];
            foreach ($wajib as $field => $pesan) {
                if (trim((string) ($validated[$field] ?? '')) === '') {
                    $errors[$field] = $pesan;
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return SumberDana::dengan($sumberDana, function () use ($validated, $sekolahId, $request, $sumberDana): RedirectResponse {
            // Isolasi sumber dana: barang wajib ada di tabel sumber dana ini.
            $barangIds = collect($validated['details'])->pluck('barang_id')->all();
            $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

            foreach ($validated['details'] as $index => $detail) {
                $barang = $barangs->get($detail['barang_id']);
                if (! $barang) {
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
                    'nilai_total' => (int) $d['jumlah'] * (int) $d['harga_satuan'],
                    'merek_type' => $d['merek_type'] ?? null,
                    'keterangan' => $d['keterangan'] ?? null,
                ])
                ->all();

            if ($validated['is_saldo_awal'] ?? false) {
                $this->saldoAwal->create($validated, $sekolahId, $request->user(), $details, $barangs->all());
            } else {
                $this->simpanPenerimaanBiasa($validated, $sekolahId, $details);
            }

            return back()->with('success', 'Penerimaan barang berhasil disimpan.');
        });
    }

    public function destroy(Request $request, Penerimaan $penerimaan): RedirectResponse
    {
        $this->authorizeSekolah($request, $penerimaan);

        try {
            if ($penerimaan->is_saldo_awal) {
                $this->saldoAwal->delete($penerimaan, $request->user());
                $pesan = 'Saldo awal berhasil dihapus.';
            } else {
                $this->penerimaan->hapus($penerimaan, $request->user());
                $pesan = 'Penerimaan berhasil dihapus.';
            }
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $pesan);
    }

    /**
     * Penerimaan biasa (bukan saldo awal): tanpa aturan uniqueness,
     * cukup header + detail dalam satu transaction.
     *
     * @param  array<int, array{barang_id:string,jumlah:int,harga_satuan:int,merek_type:?string,keterangan:?string}>  $details
     */
    private function simpanPenerimaanBiasa(array $validated, string $sekolahId, array $details): void
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
