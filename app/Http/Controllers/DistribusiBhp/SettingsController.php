<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Services\DistribusiBhp\SettingsService;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const FORMAT_FIELDS = [
        'format_nomor',
        'format_nomor_nota_permintaan',
        'format_nomor_surat_permintaan',
        'format_nomor_surat_penyaluran',
        'format_nomor_bast',
        'format_nomor_bap',
        'format_nomor_stok_opname',
        'format_nomor_buku_pengeluaran',
        'format_nomor_buku_penyaluran',
    ];

    private const PLACEHOLDER_SAH = ['{urut:1}', '{urut:2}', '{urut:3}', '{urut:4}', '{kode}', '{sumber_dana}', '{bulan_romawi}', '{bulan}', '{tahun}'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(Request $request): Response
    {
        $sumberDana = SumberDana::pilih(
            $request->validate([
                'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            ])['sumber_dana'] ?? null,
        );

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            return Inertia::render('DistribusiBhp/Settings/Edit', [
                'settings' => $this->settings->get($sumberDana),
                'sumberDana' => $sumberDana,
            ]);
        });
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::FORMAT_FIELDS as $field) {
            $rules[$field] = $field === 'format_nomor' ? ['required', 'string', 'max:200'] : ['nullable', 'string', 'max:200'];
        }
        $rules['sumber_dana'] = ['required', Rule::in(SumberDana::SEMUA)];

        $validated = $request->validate($rules);

        $sumberDana = SumberDana::normalisasi($validated['sumber_dana']);

        // Validasi tiap field yang diisi.
        foreach (self::FORMAT_FIELDS as $field) {
            $value = $validated[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (! str_contains($value, '{urut:')) {
                throw ValidationException::withMessages([
                    $field => 'Format nomor wajib memuat placeholder {urut:N} agar nomor dokumen unik.',
                ]);
            }

            preg_match_all('/\{[^}]*\}/', $value, $matches);
            $asing = collect($matches[0])
                ->reject(fn (string $p): bool => in_array($p, self::PLACEHOLDER_SAH, true))
                ->implode(', ');

            if ($asing !== '') {
                throw ValidationException::withMessages([
                    $field => 'Placeholder tidak dikenal: '.$asing,
                ]);
            }

            if (substr_count($value, '{') !== substr_count($value, '}')) {
                throw ValidationException::withMessages([
                    $field => 'Kurung kurawal harus berpasangan.',
                ]);
            }
        }

        $this->settings->update($validated, $sumberDana);

        return redirect()
            ->route('distribusi-bhp.settings.edit', ['sumber_dana' => $sumberDana])
            ->with('success', 'Settings '.$sumberDana.' berhasil disimpan.');
    }
}
