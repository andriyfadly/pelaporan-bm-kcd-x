<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BarangController extends Controller
{
    public function index(Request $request): Response
    {
        $sumberDana = strtoupper((string) $request->input('sumber_dana', 'BOS'));

        $items = Barang::with(['kodeRekening', 'harga'])
            ->where('sumber_dana', $sumberDana)
            ->orderBy('nama_barang')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('DistribusiBhp/Master/Barang/Index', [
            'items' => $items,
            'sumberDana' => $sumberDana,
            'kodeRekening' => KodeRekening::where('sumber_dana', $sumberDana)
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'spesifikasi_barang_nusp' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'kode_rekening_id' => ['nullable', 'uuid', 'exists:bhp_kode_rekening,id'],
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'harga_satuan' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($validated['kode_rekening_id'])) {
            $rekening = KodeRekening::findOrFail($validated['kode_rekening_id']);
            if ($rekening->sumber_dana !== $validated['sumber_dana']) {
                return back()->withErrors([
                    'kode_rekening_id' => 'Kode rekening tidak sesuai dengan sumber dana '.$validated['sumber_dana'].'.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $request) {
            $barang = Barang::create([
                'kode_barang' => $validated['kode_barang'] ?? null,
                'nama_barang' => $validated['nama_barang'],
                'spesifikasi_barang_nusp' => $validated['spesifikasi_barang_nusp'] ?? null,
                'satuan' => $validated['satuan'] ?? null,
                'kode_rekening_id' => $validated['kode_rekening_id'] ?? null,
                'sumber_dana' => $validated['sumber_dana'],
            ]);

            $barang->harga()->create([
                'harga_satuan' => $validated['harga_satuan'],
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function storeHarga(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'harga_satuan' => ['required', 'integer', 'min:0'],
        ]);

        $barang->harga()->create([
            'harga_satuan' => $validated['harga_satuan'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Harga barang berhasil ditambahkan.');
    }
}
