<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\KodeRekening;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KodeRekeningController extends Controller
{
    public function index(Request $request): Response
    {
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            $items = KodeRekening::query()
                ->orderBy('kode')
                ->paginate(50)
                ->withQueryString();

            return Inertia::render('DistribusiBhp/Master/KodeRekening/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:255'],
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'uraian_kode_barang' => ['nullable', 'string', 'max:255'],
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
        ]);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        return SumberDana::dengan($sumberDana, function () use ($validated, $sumberDana): RedirectResponse {
            if (KodeRekening::where('kode', $validated['kode'])->exists()) {
                return back()->withErrors([
                    'kode' => 'Kode rekening sudah ada untuk sumber dana '.$sumberDana.'.',
                ])->withInput();
            }

            KodeRekening::create([
                'kode' => $validated['kode'],
                'nama' => $validated['nama'],
                'kode_barang' => $validated['kode_barang'] ?? null,
                'uraian_kode_barang' => $validated['uraian_kode_barang'] ?? null,
            ]);

            return back()->with('success', 'Kode rekening berhasil ditambahkan.');
        });
    }
}
