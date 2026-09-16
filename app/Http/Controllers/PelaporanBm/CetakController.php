<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Exports\CetakBmExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CetakController extends Controller
{
    use ResolvesSekolah;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $sekolahId = $this->resolveSekolahId($request);
        $bulan = (int) ($request->input('bulan') ?: date('n'));

        $years = Realisasi::whereNotNull('ba_tgl')
            ->pluck('ba_tgl')
            ->map(fn ($d) => (int) date('Y', strtotime((string) $d)))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($years)) {
            $years = [(int) date('Y')];
        }

        $sekolah = $sekolahId ? Sekolah::find($sekolahId) : Sekolah::first();
        if (! $sekolah) {
            $sekolah = new Sekolah([
                'id' => '',
                'nama_sekolah' => 'Belum Ada Sekolah',
                'kota_kab' => '-',
            ]);
        }

        $items = $sekolah->id ? Spj::where('sekolah_id', $sekolah->id)
            ->where('bulan_realisasi', $bulan)
            ->orderBy('no_spk')
            ->get() : collect();

        $sekolahs = ! $user->sekolah_id
            ? Sekolah::orderBy('nama_sekolah')->select('id', 'nama_sekolah')->get()
            : [];

        return Inertia::render('PelaporanBm/Cetak/Index', [
            'years' => $years,
            'defaultBulan' => $bulan,
            'defaultTahun' => (int) ($request->input('tahun') ?: $years[0]),
            'sekolah' => $sekolah,
            'sekolahs' => $sekolahs,
            'items' => $items,
            'bulan' => $bulan,
            'totalNominal' => (float) $items->sum('nilai_perolehan'),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $bulan = (int) $request->input('bulan', date('n'));
        $tahun = (int) $request->input('tahun', date('Y'));

        $query = Realisasi::where('bulan_realisasi', $bulan)
            ->where(function ($q) use ($tahun) {
                $q->whereYear('ba_tgl', $tahun)
                    ->orWhereNull('ba_tgl');
            });

        if ($request->user()->sekolah_id) {
            $query->where('sekolah_id', $request->user()->sekolah_id);
        }

        $count = $query->count();

        return response()->json(['total_rows' => $count]);
    }

    public function unduh(Request $request): BinaryFileResponse
    {
        $bulan = (int) $request->input('bulan', date('n'));
        $tahun = (int) $request->input('tahun', date('Y'));

        $filename = "Daftar_Pengadaan_Belanja_Modal_Bulan_{$bulan}_{$tahun}.xlsx";

        $query = Realisasi::with(['sekolah', 'acuan'])
            ->where('bulan_realisasi', $bulan)
            ->where(function ($q) use ($tahun) {
                $q->whereYear('ba_tgl', $tahun)
                    ->orWhereNull('ba_tgl');
            });

        if ($request->user()->sekolah_id) {
            $query->where('sekolah_id', $request->user()->sekolah_id);
        }

        $items = $query->orderBy('sekolah_id')
            ->orderBy('ba_tgl')
            ->get();

        $namaSekolah = $request->user()->sekolah?->nama_sekolah
            ?? $items->first()?->sekolah?->nama_sekolah
            ?? 'SEMUA SEKOLAH';

        return Excel::download(
            new CetakBmExport($items, $namaSekolah, $bulan, $tahun),
            $filename
        );
    }
}
