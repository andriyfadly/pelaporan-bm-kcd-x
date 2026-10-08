<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Penyedia;
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

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = strtoupper((string) $request->input('sumber_dana', 'BOS'));

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

        // Isolasi sumber dana sebelum transaksi.
        $barangIds = collect($validated['details'])->pluck('barang_id')->all();
        $barangs = Barang::whereIn('id', $barangIds)->get()->keyBy('id');

        foreach ($validated['details'] as $index => $detail) {
            $barang = $barangs->get($detail['barang_id']);
            if ($barang && $barang->sumber_dana !== $validated['sumber_dana']) {
                throw ValidationException::withMessages([
                    "details.{$index}.barang_id" => 'Barang '.$barang->nama_barang.' tidak sesuai dengan sumber dana '.$validated['sumber_dana'].'.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $sekolahId, $barangs) {
            $penerimaan = Penerimaan::create([
                'sekolah_id' => $sekolahId,
                'no_nota_invoice' => $validated['no_nota_invoice'] ?? null,
                'no_bast' => $validated['no_bast'] ?? null,
                'tanggal_bast' => $validated['tanggal_bast'] ?? null,
                'no_kwitansi' => $validated['no_kwitansi'] ?? null,
                'penyedia_id' => $validated['penyedia_id'] ?? null,
                'tanggal_titimangsa' => $validated['tanggal_titimangsa'] ?? null,
                'is_saldo_awal' => $validated['is_saldo_awal'] ?? false,
                'periode_tahun' => $validated['periode_tahun'] ?? null,
                'sumber_dana' => $validated['sumber_dana'],
            ]);

            foreach ($validated['details'] as $detail) {
                /** @var Barang $barang */
                $barang = $barangs->get($detail['barang_id']);

                $penerimaan->details()->create([
                    'barang_id' => $barang->id,
                    'jumlah' => $detail['jumlah'],
                    'harga_satuan' => $detail['harga_satuan'],
                    'nilai_total' => $detail['jumlah'] * $detail['harga_satuan'],
                    'merek_type' => $detail['merek_type'] ?? null,
                    'keterangan' => $detail['keterangan'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Penerimaan barang berhasil disimpan.');
    }
}
