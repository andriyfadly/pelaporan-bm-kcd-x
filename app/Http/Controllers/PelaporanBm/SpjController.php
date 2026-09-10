<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\Master\KodeBarang;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpjController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $bulan = (int) ($request->input('bulan') ?: date('n'));
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $mode = $request->input('mode', '');

        $query = Spj::with(['acuan', 'sekolah'])
            ->where('bulan_realisasi', $bulan);

        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        $items = $query->orderBy('no_spk')->get();

        $isLocked = false;
        $statusKirim = 'draft';
        if ($sekolahId) {
            $kunci = KunciLaporan::where('sekolah_id', $sekolahId)
                ->where('bulan', (string) $bulan)
                ->first();
            $isLocked = $kunci?->status_kunci ?? false;
            $statusKirim = $kunci?->status_kirim ?? 'draft';
        }

        $acuanList = Acuan::where('bulan', $bulan)
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->get();
        $totalAcuan = (float) $acuanList->sum('nominal');

        return Inertia::render('PelaporanBm/Spj/Index', [
            'items' => $items,
            'acuanList' => $acuanList,
            'totalAcuan' => $totalAcuan,
            'bulan' => $bulan,
            'isLocked' => $isLocked,
            'statusKirim' => $statusKirim,
            'mode' => $mode,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');

        $validated = $request->validate([
            'no_spk' => 'required|string|max:150',
            'no_sp2d' => 'nullable|string|max:100',
            'sumber_perolehan' => 'nullable|string|max:100',
            'bulan_realisasi' => 'required|integer|between:1,12',
            'kategori' => 'nullable|string|max:50',
            'ba_no' => 'nullable|string|max:150',
            'ba_tgl' => 'nullable|date',
            'kode_barang' => 'required|string|max:50',
            'nama_barang' => 'required|string|max:255',
            'jenis_aset' => 'required|string|max:100',
            'merk_tipe' => 'nullable|string|max:255',
            'no_sertifikat' => 'nullable|string|max:100',
            'ukuran_bangunan' => 'nullable|string|max:100',
            'satuan' => 'nullable|string|max:50',
            'volume' => 'required|numeric|min:0.01',
            'harga_satuan' => 'required|numeric|min:0',
            'acuan_id' => 'nullable|uuid|exists:pelaporan_bm_acuan,id',
        ]);

        // Check lock status
        $locked = KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', (string) $validated['bulan_realisasi'])
            ->value('status_kunci');

        if ($locked) {
            return back()->with('error', 'Laporan bulan ini telah dikunci oleh dinas.');
        }

        $validated['sekolah_id'] = $sekolahId;
        $validated['nilai_perolehan'] = $validated['volume'] * $validated['harga_satuan'];

        Spj::create($validated);

        return back()->with('success', 'Data SPJ berhasil disimpan.');
    }

    public function destroy(Spj $spj, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->sekolah_id && $spj->sekolah_id !== $user->sekolah_id) {
            abort(403, 'Tidak memiliki akses');
        }

        $locked = KunciLaporan::where('sekolah_id', $spj->sekolah_id)
            ->where('bulan', (string) $spj->bulan_realisasi)
            ->value('status_kunci');

        if ($locked) {
            return back()->with('error', 'Laporan bulan ini terkunci.');
        }

        $spj->delete();

        return back()->with('success', 'Data item SPJ berhasil dihapus.');
    }

    public function destroySpk(Request $request, string $no_spk): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $locked = KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', (string) $bulan)
            ->value('status_kunci');

        if ($locked) {
            return back()->with('error', 'Laporan bulan ini terkunci.');
        }

        Spj::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('no_spk', $no_spk)
            ->delete();

        return back()->with('success', 'Seluruh data dokumen SPK berhasil dihapus.');
    }

    public function update(Request $request, Spj $spj): RedirectResponse
    {
        $user = $request->user();

        if ($user->sekolah_id && $spj->sekolah_id !== $user->sekolah_id) {
            abort(403, 'Tidak memiliki akses');
        }

        $locked = KunciLaporan::where('sekolah_id', $spj->sekolah_id)
            ->where('bulan', (string) $spj->bulan_realisasi)
            ->value('status_kunci');

        if ($locked) {
            return back()->with('error', 'Laporan bulan ini telah dikunci oleh dinas.');
        }

        $validated = $request->validate([
            'no_spk' => 'required|string|max:150',
            'no_sp2d' => 'nullable|string|max:100',
            'sumber_perolehan' => 'nullable|string|max:100',
            'bulan_realisasi' => 'required|integer|between:1,12',
            'kategori' => 'nullable|string|max:50',
            'ba_no' => 'nullable|string|max:150',
            'ba_tgl' => 'nullable|date',
            'kode_barang' => 'required|string|max:50',
            'nama_barang' => 'required|string|max:255',
            'jenis_aset' => 'required|string|max:100',
            'merk_tipe' => 'nullable|string|max:255',
            'no_sertifikat' => 'nullable|string|max:100',
            'ukuran_bangunan' => 'nullable|string|max:100',
            'satuan' => 'nullable|string|max:50',
            'volume' => 'required|numeric|min:0.01',
            'harga_satuan' => 'required|numeric|min:0',
            'acuan_id' => 'nullable|uuid|exists:pelaporan_bm_acuan,id',
        ]);

        $validated['nilai_perolehan'] = $validated['volume'] * $validated['harga_satuan'];
        $spj->update($validated);

        return back()->with('success', 'Data SPJ berhasil diperbarui.');
    }

    public function toggleRealisasi(Spj $spj): RedirectResponse
    {
        $spj->update(['is_realisasi' => ! $spj->is_realisasi]);

        return back()->with('success', 'Status realisasi berhasil diperbarui.');
    }

    public function kirimLaporan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) $request->input('bulan', date('n'));

        if (! $sekolahId) {
            return back()->with('error', 'Sekolah tidak ditemukan.');
        }

        KunciLaporan::updateOrCreate(
            ['sekolah_id' => $sekolahId, 'bulan' => (string) $bulan],
            [
                'status_kirim' => 'menunggu_approval',
                'dikirim_pada' => now(),
            ]
        );

        return back()->with('success', 'Laporan bulan ini berhasil dikirim ke KCD untuk verifikasi.');
    }

    public function unduh(Request $request): StreamedResponse
    {
        $user = $request->user();
        $sekolahId = $user->sekolah_id ?: $request->input('sekolah_id');
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $query = Spj::with('sekolah')->where('bulan_realisasi', $bulan);
        if ($sekolahId) {
            $query->where('sekolah_id', $sekolahId);
        }

        $items = $query->orderBy('no_spk')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"rekap_bm_bulan_{$bulan}.csv\"",
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['No SPK', 'No SP2D', 'Sumber', 'Kode Barang', 'Nama Barang', 'Jenis Aset', 'Merk/Tipe', 'Satuan', 'Volume', 'Harga Satuan', 'Nilai Perolehan']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->no_spk,
                    $item->no_sp2d,
                    $item->sumber_perolehan,
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->jenis_aset,
                    $item->merk_tipe,
                    $item->satuan,
                    $item->volume,
                    $item->harga_satuan,
                    $item->nilai_perolehan,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function cariBarang(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $masterResults = KodeBarang::select('kode_barang', 'uraian as nama_barang', 'jenis_aset', 'satuan')
            ->where(function ($query) use ($q) {
                $query->where('kode_barang', 'like', "%{$q}%")
                    ->orWhere('uraian', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get();

        if ($masterResults->isNotEmpty()) {
            return response()->json($masterResults);
        }

        $results = Spj::select('kode_barang', 'nama_barang', 'jenis_aset', 'satuan')
            ->where(function ($query) use ($q) {
                $query->where('kode_barang', 'like', "%{$q}%")
                    ->orWhere('nama_barang', 'like', "%{$q}%");
            })
            ->distinct()
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}
