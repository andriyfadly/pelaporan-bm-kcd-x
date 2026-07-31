<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('report:reconcile-period {bulan : Reporting month (1-12)}')]
#[Description('Read-only reconciliation for a legacy reporting period')]
class ReconcileReportPeriod extends Command
{
    public function handle(): int
    {
        $month = filter_var($this->argument('bulan'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12],
        ]);

        if ($month === false) {
            $this->error('The bulan argument must be between 1 and 12.');

            return self::FAILURE;
        }

        $crossSchoolReferences = DB::table('realisasi_barang_sekolah as realisasi')
            ->join('master_barang_sekolah as master', 'realisasi.id_master_barang', '=', 'master.id')
            ->where('realisasi.bulan_realisasi', $month)
            ->whereColumn('realisasi.id_sekolah', '!=', 'master.id_sekolah')
            ->count();
        $moneyMismatches = DB::table('realisasi_barang_sekolah')
            ->where('bulan_realisasi', $month)
            ->whereRaw('nilai_perolehan <> ROUND(volume * harga_satuan, 2)')
            ->count();

        $this->line("cross-school master references: {$crossSchoolReferences}");
        $this->line("money mismatches: {$moneyMismatches}");

        if ($crossSchoolReferences !== 0 || $moneyMismatches !== 0) {
            return self::FAILURE;
        }

        $this->info('Reconciliation passed.');

        return self::SUCCESS;
    }
}
