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

        // Statistik dipisah per sumber dana (tabelnya memang terpisah). Satu
        // angka gabungan BOS+BOPD tidak bisa ditindaklanjuti: operator perlu
        // tahu dana mana yang menumpuk draft atau menunggu review.
        $perDana = [];

        foreach (SumberDana::SEMUA as $dana) {
            $perDana[$dana] = SumberDana::dengan($dana, function () use ($sekolahId, $divisiId): array {
                $pengajuan = Pengajuan::query()
                    ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                    ->when($divisiId, fn ($q) => $q->where('divisi_id', $divisiId));
                $penerimaan = Penerimaan::query()->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId));

                return [
                    'totalBarang' => Barang::count(),
                    'pengajuanSubmitted' => (clone $pengajuan)->where('status', 'submitted')->count(),
                    'pengajuanApproved' => (clone $pengajuan)->where('status', 'approved')->count(),
                    'totalPenerimaan' => (clone $penerimaan)->count(),
                ];
            });
        }

        return Inertia::render('DistribusiBhp/Dashboard', [
            'perDana' => $perDana,
        ]);
    }
}
