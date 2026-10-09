<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BarangController extends Controller
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
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
        ])['sumber_dana'] ?? SumberDana::BOS;
        $sumberDana = SumberDana::normalisasi($sumberDana);

        return SumberDana::dengan($sumberDana, function () use ($sumberDana): Response {
            $items = Barang::with(['kodeRekening', 'harga'])
                ->orderBy('nama_barang')
                ->get();

            $stats = [
                'total' => $items->count(),
                'stok_terpakai' => $items->filter(fn (Barang $b) => $b->harga->isNotEmpty())->count(),
                'bulan_ini' => Barang::where('created_at', '>=', now()->startOfMonth())->count(),
            ];

            return Inertia::render('DistribusiBhp/Master/Barang/Index', [
                'items' => $items,
                'sumberDana' => $sumberDana,
                'stats' => $stats,
                'kodeBarangOptions' => static::KODE_BARANG_OPTIONS,
                'kodeRekening' => KodeRekening::orderBy('kode')->get(['id', 'kode', 'nama', 'kode_barang', 'uraian_kode_barang']),
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

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'kode_barang' => ['nullable', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'spesifikasi_barang_nusp' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'kode_rekening_id' => ['nullable', 'uuid'],
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
        ]);

        if (! empty($validated['kode_rekening_id'])
            && ! KodeRekening::whereKey($validated['kode_rekening_id'])->exists()) {
            return back()->withErrors([
                'kode_rekening_id' => 'Kode rekening tidak sesuai dengan sumber dana '.$barang->sumber_dana.'.',
            ])->withInput();
        }

        $barang->update([
            'kode_barang' => $validated['kode_barang'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'spesifikasi_barang_nusp' => $validated['spesifikasi_barang_nusp'] ?? null,
            'satuan' => $validated['satuan'] ?? null,
            'kode_rekening_id' => $validated['kode_rekening_id'] ?? null,
        ]);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $dipakai = PenerimaanDetail::where('barang_id', $barang->id)->exists()
            || PengajuanDetail::where('barang_id', $barang->id)->exists();

        if ($dipakai) {
            return back()->with('error', 'Barang tidak dapat dihapus karena masih dipakai pada penerimaan/pengajuan.');
        }

        DB::transaction(function () use ($barang) {
            $barang->harga()->delete();
            $barang->delete();
        });

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    public function hargaIndex(Barang $barang): Response
    {
        return Inertia::render('DistribusiBhp/Master/Barang/KelolaHarga', [
            'barang' => $barang->only(['id', 'kode_barang', 'nama_barang', 'spesifikasi_barang_nusp', 'satuan']),
            'sumberDana' => $barang->sumber_dana,
            'hargaList' => $barang->harga()->orderByDesc('created_at')->get(['id', 'harga_satuan', 'created_at']),
        ]);
    }

    public function storeHarga(Request $request, Barang $barang): RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(SumberDana::SEMUA)],
            'harga_satuan' => ['required', 'integer', 'min:1'],
        ]);

        $barang->harga()->create([
            'harga_satuan' => $validated['harga_satuan'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Harga barang berhasil ditambahkan.');
    }

    public function updateHarga(Request $request, HargaBarang $harga): RedirectResponse
    {
        $validated = $request->validate([
            'harga_satuan' => ['required', 'integer', 'min:1'],
        ]);

        $harga->update(['harga_satuan' => $validated['harga_satuan']]);

        return back()->with('success', 'Harga barang berhasil diperbarui.');
    }

    public function destroyHarga(HargaBarang $harga): RedirectResponse
    {
        $harga->delete();

        return back()->with('success', 'Harga barang berhasil dihapus.');
    }
}
