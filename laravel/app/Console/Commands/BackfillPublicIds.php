<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:backfill-public-ids {--chunk=500 : Rows per batch} {--dry-run : Count rows without writing}')]
#[Description('Backfill UUIDv7 public identifiers on legacy tables')]
class BackfillPublicIds extends Command
{
    private const TABLES = [
        'kode_sekolah',
        'users',
        'data_barang_acuan',
        'laporan_realisasi',
        'master_barang_sekolah',
        'realisasi_barang_sekolah',
    ];

    public function handle(): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($chunkSize === false) {
            $this->error('The --chunk option must be a positive integer.');

            return self::FAILURE;
        }

        foreach (self::TABLES as $table) {
            $query = DB::table($table)->whereNull('public_id');

            if ($this->option('dry-run')) {
                $this->line("{$table}: {$query->count()} pending");

                continue;
            }

            $updated = 0;
            $query->select('id')->chunkById($chunkSize, function ($rows) use ($table, &$updated): void {
                DB::transaction(function () use ($rows, $table, &$updated): void {
                    foreach ($rows as $row) {
                        $updated += DB::table($table)
                            ->where('id', $row->id)
                            ->whereNull('public_id')
                            ->update(['public_id' => (string) Str::uuid7()]);
                    }
                });
            });

            $this->line("{$table}: {$updated} updated");
        }

        return self::SUCCESS;
    }
}
