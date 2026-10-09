<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Penyedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PenyediaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('DistribusiBhp/Master/Penyedia/Index', [
            'items' => Penyedia::orderBy('nama')->paginate(50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:bhp_penyedia,nama'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        Penyedia::create($validated);

        return back()->with('success', 'Penyedia berhasil ditambahkan.');
    }

    public function update(Request $request, Penyedia $penyedia): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('bhp_penyedia', 'nama')->ignore($penyedia->id)],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $penyedia->update($validated);

        return back()->with('success', 'Penyedia berhasil diperbarui.');
    }

    public function destroy(Penyedia $penyedia): RedirectResponse
    {
        if ($penyedia->penerimaan()->exists()) {
            return back()->with('error', 'Penyedia tidak dapat dihapus karena masih dipakai pada penerimaan.');
        }

        $penyedia->delete();

        return back()->with('success', 'Penyedia berhasil dihapus.');
    }
}
