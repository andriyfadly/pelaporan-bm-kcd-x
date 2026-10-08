<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil sekolah (nama, NPSN, alamat, kontak): master data per sekolah,
 * dikelola operator sekolah sendiri — sekolah_id selalu dari user login
 * (tenant-scoped), bukan dari input.
 */
class ProfilSekolahController extends Controller
{
    use ResolvesSekolah;

    public function edit(Request $request): Response
    {
        $sekolah = Sekolah::findOrFail($this->resolveSekolahId($request));

        return Inertia::render('MasterData/ProfilSekolah/Edit', [
            'sekolah' => $sekolah->only([
                'id', 'nama_sekolah', 'npsn', 'kota_kab', 'alamat', 'telepon', 'website', 'email',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $sekolah = Sekolah::findOrFail($this->resolveSekolahId($request));

        // Nama/NPSN/kota bagian identitas master (dikelola admin); operator
        // hanya mengisi data profil kontak.
        $validated = $request->validate([
            'alamat' => ['nullable', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $sekolah->update($validated);

        activity('sistem')
            ->event('kelola-profil-sekolah')
            ->withProperties([
                'ringkasan' => "Perbarui profil sekolah {$sekolah->nama_sekolah}",
                'sekolah_id' => $sekolah->id,
            ])
            ->log('kelola-profil-sekolah');

        return back()->with('success', 'Profil sekolah berhasil disimpan.');
    }
}
