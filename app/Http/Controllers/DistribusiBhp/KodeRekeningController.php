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
    private const KODE_BARANG_OPTIONS = [
        ['kode' => '1.1.7.01.01', 'uraian' => 'Bahan'],
        ['kode' => '1.1.7.01.02', 'uraian' => 'Suku Cadang'],
        ['kode' => '1.1.7.01.03', 'uraian' => 'Alat/Bahan Untuk Kegiatan Kantor'],
        ['kode' => '1.1.7.01.04', 'uraian' => 'Obat-Obatan'],
        ['kode' => '1.1.7.01.07', 'uraian' => 'Naturan dan Pakan'],
    ];

    public function index(Request $request): Response
    {
        $sumberDana = SumberDana::pilih(
            $request->validate([
                'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            ])['sumber_dana'] ?? null,
        );

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            $items = KodeRekening::query()
                ->orderBy('kode')
                ->paginate(50)
                ->withQueryString();

            return Inertia::render('DistribusiBhp/Master/KodeRekening/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'kodeBarangOptions' => static::KODE_BARANG_OPTIONS,
            ]);
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:255'],
            'kode_barang' => ['nullable', 'string', Rule::in(array_column(static::KODE_BARANG_OPTIONS, 'kode'))],
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

    public function batchAssign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.id' => ['required', 'uuid', 'exists:'.KodeRekening::class.',id'],
            'assignments.*.kode_barang' => ['nullable', 'string', Rule::in(array_column(static::KODE_BARANG_OPTIONS, 'kode'))],
            'assignments.*.uraian_kode_barang' => ['nullable', 'string', 'max:255'],
        ]);

        $count = 0;
        foreach ($validated['assignments'] as $assignment) {
            KodeRekening::whereKey($assignment['id'])->update([
                'kode_barang' => $assignment['kode_barang'] ?? null,
                'uraian_kode_barang' => $assignment['uraian_kode_barang'] ?? null,
            ]);
            $count++;
        }

        return back()->with('success', "{$count} kode rekening berhasil di-assign kode barang.");
    }
}
