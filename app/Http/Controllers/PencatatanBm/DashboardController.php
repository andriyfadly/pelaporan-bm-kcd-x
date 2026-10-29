<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\SyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        // Agregasi di database: dashboard tidak perlu menarik semua unit ke memori.
        $aktif = fn () => InventarisUnit::query()
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', InventarisUnit::STATUS_AKTIF);

        $ringkasanUnit = $aktif()
            ->toBase()
            ->selectRaw('COUNT(*) as total_unit')
            ->selectRaw('COALESCE(SUM(nilai_perolehan_unit), 0) as total_nilai')
            ->selectRaw('COUNT(DISTINCT kode_barang) as jenis_barang')
            ->first();

        $perKondisi = $aktif()
            ->toBase()
            ->selectRaw('kondisi, COUNT(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi');

        return Inertia::render('PencatatanBm/Dashboard', [
            'ringkasan' => [
                'totalUnit' => (int) $ringkasanUnit->total_unit,
                'totalNilai' => (float) $ringkasanUnit->total_nilai,
                'kondisi' => array_merge(
                    array_fill_keys(array_keys(InventarisUnit::KONDISI), 0),
                    $perKondisi->map(fn ($jumlah) => (int) $jumlah)->all(),
                ),
                'jenisBarang' => (int) $ringkasanUnit->jenis_barang,
                'ruang' => Ruang::when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))->count(),
                'pegawai' => Pegawai::when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))->count(),
            ],
            'perKodeBarang' => $aktif()
                ->toBase()
                ->selectRaw('kode_barang, MAX(nama_barang) as nama_barang, COUNT(*) as jumlah, SUM(nilai_perolehan_unit) as nilai')
                ->groupBy('kode_barang')
                ->orderByDesc('jumlah')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'kode_barang' => (string) $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'jumlah' => (int) $row->jumlah,
                    'nilai' => (float) $row->nilai,
                ]),
            'sinkronisasiTerakhir' => SyncLog::query()
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->latest('created_at')
                ->first(['status', 'dibuat', 'surplus', 'perlu_review', 'created_at']),
        ]);
    }
}
