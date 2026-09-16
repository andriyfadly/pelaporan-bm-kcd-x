<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Exports\RealisasiBmExport;
use App\Http\Controllers\Controller;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RealisasiController extends Controller
{
    private function applyFilters(Request $request): Builder
    {
        $user = $request->user();
        $filterBarang = trim($request->input('filter_barang', ''));
        $filterBulan = (int) $request->input('filter_bulan', 0);
        $filterTahun = (int) $request->input('filter_tahun', 0);

        // Legacy data_realisasi.php: baca dari tabel realisasi (pelaporan_bm_realisasi)
        $query = Realisasi::query()
            ->with('sekolah:id,nama_sekolah');

        if ($user->sekolah_id) {
            $query->where('sekolah_id', $user->sekolah_id);
        }

        if (! empty($filterBarang)) {
            $escapedBarang = addcslashes($filterBarang, '%_\\');
            $query->where(function ($q) use ($escapedBarang) {
                $q->where('nama_barang', 'like', "%{$escapedBarang}%")
                    ->orWhere('kode_barang', 'like', "%{$escapedBarang}%");
            });
        }

        if ($filterBulan >= 1 && $filterBulan <= 12) {
            $query->where('bulan_realisasi', $filterBulan);
        }

        if ($filterTahun > 0) {
            $query->whereYear('ba_tgl', $filterTahun);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $query = $this->applyFilters($request);

        $totalNilaiPerolehan = (float) (clone $query)->sum('nilai_perolehan');
        // Legacy: ORDER BY ba_tgl DESC, id DESC
        $items = $query->orderByDesc('ba_tgl')->orderByDesc('id')->paginate(25)->withQueryString();

        $availableYears = Realisasi::query()
            ->whereNotNull('ba_tgl')
            ->when($request->user()->sekolah_id, fn ($q) => $q->where('sekolah_id', $request->user()->sekolah_id))
            ->pluck('ba_tgl')
            ->map(fn ($tgl) => (int) date('Y', strtotime($tgl)))
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('PelaporanBm/Realisasi/Index', [
            'items' => $items,
            'filters' => [
                'filter_barang' => trim($request->input('filter_barang', '')),
                'filter_bulan' => (int) $request->input('filter_bulan', 0) ?: null,
                'filter_tahun' => (int) $request->input('filter_tahun', 0) ?: null,
            ],
            'totalNilaiPerolehan' => $totalNilaiPerolehan,
            'availableYears' => $availableYears,
        ]);
    }

    public function unduh(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        // Legacy data_realisasi.php: laporan diurut ASC, satu file .xlsx;
        // data kosong -> 204 + cookie download_status=empty (tanpa file)
        $records = $this->applyFilters($request)->orderBy('ba_tgl')->orderBy('id')->get()
            ->sortBy(fn ($r) => [$r->sekolah?->nama_sekolah ?? '', $r->ba_tgl ?? '', $r->id])->values();

        if ($records->isEmpty()) {
            return response()->noContent(204)->withCookie(cookie('download_status', 'empty', 1, '/'));
        }

        $user = $request->user();
        $namaSekolah = $user->sekolah?->nama_sekolah ?? $records->first()?->sekolah?->nama_sekolah ?? 'SEMUA SEKOLAH';
        $filterBulan = (int) $request->input('filter_bulan', 0);
        $filterTahun = (int) $request->input('filter_tahun', 0);

        $filename = 'Daftar_Pengadaan_Belanja_Modal_Bulan_'.($filterBulan > 0 ? $filterBulan : 'All').'_'.($filterTahun > 0 ? $filterTahun : date('Y')).'.xlsx';

        activity('sistem')
            ->event('unduh-realisasi')
            ->withProperties([
                'ringkasan' => "Unduh {$filename} ({$records->count()} baris)",
                'sekolah_id' => $user->sekolah_id,
                'bulan' => $filterBulan,
                'tahun' => $filterTahun,
                'jumlah' => $records->count(),
            ])
            ->log('unduh-realisasi');

        $response = Excel::download(
            new RealisasiBmExport($records, $namaSekolah, $filterTahun),
            $filename
        );

        $response->headers->setCookie(cookie('download_status', 'complete', 1, '/'));

        return $response;
    }
}
