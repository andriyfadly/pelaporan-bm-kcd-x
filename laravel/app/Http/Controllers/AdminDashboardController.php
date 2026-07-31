<?php

namespace App\Http\Controllers;

use App\Models\DataBarangAcuan;
use App\Models\RealisasiBarangSekolah;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $month = $request->integer('bulan', (int) now()->format('n'));
        $year = $request->integer('tahun', (int) now()->format('Y'));

        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 422);

        $targetSchoolIds = DataBarangAcuan::query()
            ->where('bulan', $month)
            ->where(fn ($query) => $query->whereYear('created_at', $year)->orWhereYear('tanggal', $year))
            ->distinct()
            ->pluck('id_sekolah')
            ->map(fn ($id): string => (string) $id);

        $completedSchoolIds = RealisasiBarangSekolah::query()
            ->where('bulan_realisasi', $month)
            ->where('is_realisasi', true)
            ->where(fn ($query) => $query->whereYear('ba_tgl', $year)->orWhereYear('created_at', $year))
            ->distinct()
            ->pluck('id_sekolah')
            ->map(fn ($id): string => (string) $id);

        $completed = $targetSchoolIds->intersect($completedSchoolIds)->count();
        $total = $targetSchoolIds->count();

        return view('dashboard.admin', [
            'month' => $month,
            'year' => $year,
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
        ]);
    }
}
