<?php

namespace App\Http\Controllers;

use App\Models\DataBarangAcuan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcuanController extends Controller
{
    public function __invoke(Request $request): View
    {
        $schoolId = (string) $request->user()->id_sekolah;
        $month = $request->string('bulan')->trim()->value();
        $search = $request->string('search_satuan')->trim()->value();

        $query = DataBarangAcuan::query()
            ->where('id_sekolah', $schoolId)
            ->when($month !== '', fn ($query) => $query->where('bulan', $month))
            ->when($search !== '', fn ($query) => $query->where('satuan_pendidikan', 'like', "%{$search}%"));

        $totalNominal = (clone $query)->sum('nominal');
        $totalSchools = (clone $query)->distinct('npsn')->count('npsn');
        $rows = $query->orderByDesc('id')->paginate(50)->withQueryString();

        return view('acuan.index', compact('rows', 'totalNominal', 'totalSchools'));
    }
}
