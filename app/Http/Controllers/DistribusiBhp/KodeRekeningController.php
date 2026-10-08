<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\KodeRekening;
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
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
        ])['sumber_dana'] ?? 'BOS';
        $sumberDana = strtoupper((string) $sumberDana);

        $items = KodeRekening::query()
            ->where('sumber_dana', $sumberDana)
            ->orderBy('kode')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('DistribusiBhp/Master/KodeRekening/Index', [
            'items' => $items,
            'sumberDana' => $sumberDana,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:255'],
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'uraian_kode_barang' => ['nullable', 'string', 'max:255'],
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
        ]);

        $exists = KodeRekening::where('kode', $validated['kode'])
            ->where('sumber_dana', $validated['sumber_dana'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'kode' => 'Kode rekening sudah ada untuk sumber dana '.$validated['sumber_dana'].'.',
            ])->withInput();
        }

        KodeRekening::create($validated);

        return back()->with('success', 'Kode rekening berhasil ditambahkan.');
    }
}
