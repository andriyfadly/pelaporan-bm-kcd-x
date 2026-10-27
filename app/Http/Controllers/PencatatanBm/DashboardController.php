<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        $units = InventarisUnit::query()
            ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->get(['sekolah_id', 'kode_barang', 'nama_barang', 'kondisi', 'nilai_perolehan_unit']);

        return Inertia::render('PencatatanBm/Dashboard', [
            'ringkasan' => [
                'totalUnit' => $units->count(),
                'totalNilai' => (float) $units->sum('nilai_perolehan_unit'),
                'kondisi' => $this->hitungKondisi($units),
                'jenisBarang' => $units->pluck('kode_barang')->unique()->count(),
                'ruang' => Ruang::when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))->count(),
                'pegawai' => Pegawai::when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))->count(),
            ],
            'perKodeBarang' => $units
                ->groupBy('kode_barang')
                ->map(fn (Collection $rows, string $kode) => [
                    'kode_barang' => $kode,
                    'nama_barang' => $rows->first()->nama_barang,
                    'jumlah' => $rows->count(),
                    'nilai' => (float) $rows->sum('nilai_perolehan_unit'),
                ])
                ->sortByDesc('jumlah')
                ->values()
                ->take(10),
            'sinkronisasiTerakhir' => SyncLog::query()
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->latest('created_at')
                ->first(['status', 'dibuat', 'surplus', 'perlu_review', 'created_at']),
        ]);
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     * @return array<string, int>
     */
    private function hitungKondisi(Collection $units): array
    {
        $hasil = array_fill_keys(array_keys(InventarisUnit::KONDISI), 0);

        foreach ($units as $unit) {
            $hasil[$unit->kondisi] = ($hasil[$unit->kondisi] ?? 0) + 1;
        }

        return $hasil;
    }
}
