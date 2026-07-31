<?php

namespace App\Http\Controllers;

use App\Models\DataBarangAcuan;
use App\Models\LaporanRealisasi;
use App\Models\MasterBarangSekolah;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $schoolId = (string) $request->user()->id_sekolah;
        $school = Sekolah::query()->findOrFail($schoolId);

        $targets = DataBarangAcuan::query()
            ->where('id_sekolah', $schoolId)
            ->selectRaw('bulan, SUM(nominal) AS total')
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $realizations = MasterBarangSekolah::query()
            ->forSchool($schoolId)
            ->selectRaw("bulan_realisasi, SUM(nilai_perolehan) AS total, COUNT(id) AS assets, COUNT(DISTINCT NULLIF(TRIM(no_spk), '')) AS documents")
            ->groupBy('bulan_realisasi')
            ->get()
            ->keyBy('bulan_realisasi');

        $statuses = LaporanRealisasi::query()
            ->where('id_sekolah', $schoolId)
            ->pluck('status', 'bulan');

        return view('dashboard.user', compact('school', 'targets', 'realizations', 'statuses'));
    }
}
