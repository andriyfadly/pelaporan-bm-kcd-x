<?php

namespace App\Http\Controllers\PelaporanBm;

use App\Http\Controllers\Controller;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $query->where(function ($q) use ($filterBarang) {
                $q->where('nama_barang', 'like', "%{$filterBarang}%")
                    ->orWhere('kode_barang', 'like', "%{$filterBarang}%");
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

    public function unduh(Request $request): StreamedResponse
    {
        // Legacy: laporan diurut ASC
        $records = $this->applyFilters($request)->orderBy('ba_tgl')->orderBy('id')->get();

        $filename = 'Laporan_Realisasi_BM_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No',
                'Nama Sekolah',
                'No. SP2D',
                'Sumber Perolehan',
                'Kodering Belanja',
                'No. SPK / Faktur',
                'BA No',
                'BA Tgl',
                'BA Bln',
                'BA Thn',
                'Bulan Realisasi',
                'Kode Barang',
                'Nama Barang',
                'Merk/Tipe',
                'No Sertifikat',
                'Ukuran Bangunan',
                'Satuan',
                'Volume',
                'Harga Satuan',
                'Nilai Perolehan',
            ]);

            $no = 1;
            foreach ($records as $row) {
                $tgl = $row->ba_tgl ? date('d', strtotime($row->ba_tgl)) : '-';
                $bln = $row->ba_tgl ? date('m', strtotime($row->ba_tgl)) : '-';
                $thn = $row->ba_tgl ? date('Y', strtotime($row->ba_tgl)) : '-';

                fputcsv($handle, [
                    $no++,
                    $row->sekolah?->nama_sekolah ?? '-',
                    $row->no_sp2d ?? '-',
                    $row->sumber_perolehan ?? '-',
                    $row->kodering_belanja ?? '-',
                    $row->no_spk ?? '-',
                    $row->ba_no ?? '-',
                    $tgl,
                    $bln,
                    $thn,
                    $row->bulan_realisasi,
                    $row->kode_barang,
                    $row->nama_barang,
                    $row->merk_tipe ?? '-',
                    $row->no_sertifikat ?? '-',
                    $row->ukuran_bangunan ?? '-',
                    $row->satuan ?? '-',
                    $row->volume,
                    $row->harga_satuan,
                    $row->nilai_perolehan,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
