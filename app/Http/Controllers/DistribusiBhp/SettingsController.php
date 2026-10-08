<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Services\DistribusiBhp\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Placeholder yang sah pada format nomor dokumen. */
    private const PLACEHOLDER_SAH = ['{urut:1}', '{urut:2}', '{urut:3}', '{urut:4}', '{kode}', '{sumber_dana}', '{bulan_romawi}', '{bulan}', '{tahun}'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('DistribusiBhp/Settings/Edit', [
            'settings' => $this->settings->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'format_nomor' => ['required', 'string', 'max:200'],
            'paper_size' => ['nullable', Rule::in(['a4', 'letter', 'foli/f4'])],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
        ]);

        // Format tanpa placeholder menghasilkan nomor dokumen duplikat
        // untuk setiap pengajuan — tolak di sini, bukan saat generate.
        if (! str_contains($validated['format_nomor'], '{urut:')) {
            throw ValidationException::withMessages([
                'format_nomor' => 'Format nomor wajib memuat placeholder {urut:N} agar nomor dokumen unik.',
            ]);
        }

        // Semua placeholder harus termasuk daftar sah — placeholder asing
        // tidak akan pernah diganti dan bocor mentah ke dokumen.
        preg_match_all('/\{[^}]*\}/', $validated['format_nomor'], $matches);
        $asing = collect($matches[0] ?? [])
            ->reject(fn (string $p): bool => in_array($p, self::PLACEHOLDER_SAH, true))
            ->implode(', ');

        if ($asing !== '') {
            throw ValidationException::withMessages([
                'format_nomor' => 'Placeholder tidak dikenal: '.$asing,
            ]);
        }

        // Kurung tanpa pasangan.
        if (substr_count($validated['format_nomor'], '{') !== substr_count($validated['format_nomor'], '}')) {
            throw ValidationException::withMessages([
                'format_nomor' => 'Kurung kurawal harus berpasangan.',
            ]);
        }

        $this->settings->update($validated);

        return back()->with('success', 'Settings berhasil disimpan.');
    }
}
