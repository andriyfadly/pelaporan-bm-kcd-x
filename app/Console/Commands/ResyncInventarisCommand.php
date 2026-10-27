<?php

namespace App\Console\Commands;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Illuminate\Console\Command;

class ResyncInventarisCommand extends Command
{
    protected $signature = 'pencatatan-bm:resync
        {--bulan= : Bulan periode (1-12)}
        {--tahun= : Tahun periode}
        {--sekolah= : UUID sekolah (opsional; default semua sekolah)}
        {--dry-run : Tampilkan rencana tanpa menulis data}';

    protected $description = 'Sinkronkan ulang realisasi yang disetujui menjadi unit inventaris';

    public function handle(SinkronisasiInventarisService $service): int
    {
        $bulan = filter_var($this->option('bulan'), FILTER_VALIDATE_INT);
        $tahun = filter_var($this->option('tahun'), FILTER_VALIDATE_INT);

        if (! $bulan || ! $tahun || $bulan < 1 || $bulan > 12) {
            $this->error('Opsi --bulan (1-12) dan --tahun wajib diisi.');

            return self::FAILURE;
        }

        $sekolahIds = $this->option('sekolah')
            ? [(string) $this->option('sekolah')]
            : Sekolah::query()->pluck('id')->all();

        $dryRun = (bool) $this->option('dry-run');

        foreach ($sekolahIds as $sekolahId) {
            if ($dryRun) {
                $rencana = $this->rencana($sekolahId, $bulan, $tahun);
                $this->line(sprintf('[dry-run] %s → %d unit akan dibuat', $sekolahId, $rencana));

                continue;
            }

            $hasil = $service->syncPeriode($sekolahId, $bulan, $tahun);

            $this->line(sprintf(
                '%s → dibuat: %d, tetap: %d, surplus: %d, perlu review: %d',
                $sekolahId, $hasil->dibuat, $hasil->tetap, $hasil->surplus, $hasil->perluReview,
            ));
        }

        return self::SUCCESS;
    }

    private function rencana(string $sekolahId, int $bulan, int $tahun): int
    {
        $disetujui = KunciLaporan::where('sekolah_id', $sekolahId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('status_kirim', 'disetujui')
            ->exists();

        if (! $disetujui) {
            return 0;
        }

        return Realisasi::where('sekolah_id', $sekolahId)
            ->where('bulan_realisasi', $bulan)
            ->where('tahun', $tahun)
            ->get()
            ->sum(function ($realisasi) {
                $target = (int) floor((float) $realisasi->volume);
                $existing = InventarisUnit::where('realisasi_id', $realisasi->id)->count();

                return max(0, $target - $existing);
            });
    }
}
