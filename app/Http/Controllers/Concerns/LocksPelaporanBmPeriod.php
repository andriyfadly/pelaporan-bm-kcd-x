<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PelaporanBm\KunciLaporan;
use Illuminate\Support\Str;

trait LocksPelaporanBmPeriod
{
    protected function lockPelaporanPeriod(?string $sekolahId, int $bulan, ?int $tahun = null): ?KunciLaporan
    {
        if ($sekolahId === null) {
            return null;
        }

        $tahun ??= (int) now()->year;

        KunciLaporan::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $sekolahId,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status_kunci' => false,
            'status_kirim' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function pelaporanPeriodIsLocked(?KunciLaporan $kunci): bool
    {
        return $kunci !== null && ($kunci->status_kunci
            || in_array($kunci->status_kirim, ['menunggu_approval', 'disetujui'], true));
    }
}
