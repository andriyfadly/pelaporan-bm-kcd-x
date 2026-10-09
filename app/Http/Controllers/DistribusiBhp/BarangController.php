<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Support\DistribusiBhp\SumberDana;
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
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            $items = Barang::with(['kodeRekening', 'harga'])
                ->orderBy('nama_barang')
                ->paginate(50)
                ->withQueryString();

            return Inertia::render('DistribusiBhp/Master/Barang/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'kodeRekening' => KodeRekening::orderBy('kode')->get(['id', 'kode', 'nama']),
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

    public function storeHarga(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'harga_satuan' => ['required', 'integer', 'min:0'],
        ]);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);
        if ($barang->sumber_dana !== $sumberDana) {
            return back()->with('error', 'Sumber dana tidak sesuai dengan barang ini.');
        }

        $barang->harga()->create([
            'harga_satuan' => $validated['harga_satuan'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Harga barang berhasil ditambahkan.');
    }
}
