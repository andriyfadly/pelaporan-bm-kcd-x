<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Pengajuan;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        // Operator divisi hanya melihat statistik divisinya sendiri; penerimaan
        // tetap scope sekolah karena stok bukan milik divisi.
        $divisiId = $request->user()->divisi_id;

        // Statistik dashboard menjumlahkan BOS + BOPD (tabel terpisah).
        $total = [
            'totalBarang' => 0,
            'pengajuanDraft' => 0,
            'pengajuanSubmitted' => 0,
            'pengajuanApproved' => 0,
            'totalPenerimaan' => 0,
        ];

        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use (&$total, $sekolahId, $divisiId) {
                $pengajuan = Pengajuan::query()
                    ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                    ->when($divisiId, fn ($q) => $q->where('divisi_id', $divisiId));
                $penerimaan = Penerimaan::query()->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId));

                $total['totalBarang'] += Barang::count();
                $total['pengajuanDraft'] += (clone $pengajuan)->where('status', 'draft')->count();
                $total['pengajuanSubmitted'] += (clone $pengajuan)->where('status', 'submitted')->count();
                $total['pengajuanApproved'] += (clone $pengajuan)->where('status', 'approved')->count();
                $total['totalPenerimaan'] += (clone $penerimaan)->count();
            });
        }

        return Inertia::render('DistribusiBhp/Dashboard', [
            'stats' => $total,
        ]);
    }
}
