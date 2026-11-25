<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard inventaris: lima kartu seperti `index.php` acuan (total, tiga
 * kondisi, dan barang hilang). Rincian barang per kondisi dimuat saat kartu
 * diklik supaya muat awal tetap ringan.
 */
class DashboardController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        return Inertia::render('PencatatanBm/Dashboard', [
            'ringkasan' => $this->ringkasan($this->resolveSekolahId($request)),
        ]);
    }

    /**
     * Statistik untuk polling dashboard (acuan: `index.php?ajax_stats=1`).
     */
    public function statistik(Request $request): JsonResponse
    {
        return response()->json([
            'ringkasan' => $this->ringkasan($this->resolveSekolahId($request)),
        ]);
    }

    /**
     * @return array{totalUnit: int, kondisi: array<string, int>}
     */
    private function ringkasan(?string $sekolahId): array
    {
        $perKondisi = InventarisUnit::query()
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->toBase()
            ->selectRaw('kondisi, COUNT(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi');

        $kondisi = array_merge(
            array_fill_keys(array_keys(InventarisUnit::KONDISI), 0),
            $perKondisi->map(fn ($jumlah): int => (int) $jumlah)->all(),
        );

        return [
            'totalUnit' => array_sum($kondisi),
            'kondisi' => $kondisi,
        ];
    }

    /**
     * Rincian barang untuk satu kondisi, dipakai modal dashboard.
     *
     * @return JsonResponse
     */
    public function rincian(Request $request, string $kode)
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $baris = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->where('sekolah_id', $sekolahId)
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->where('kondisi', $kode)
            ->orderBy('nama_barang')
            ->orderBy('nomor_register')
            ->get()
            ->map(fn (InventarisUnit $unit): array => [
                'nama_barang' => $unit->nama_barang,
                'merk_tipe' => $unit->merk_tipe,
                'nomor_register' => $unit->nomor_register,
                'ruang' => $unit->ruang?->nama_ruang,
                'pegawai' => $unit->pegawai?->nama_lengkap,
            ]);

        return response()->json($baris);
    }
}
