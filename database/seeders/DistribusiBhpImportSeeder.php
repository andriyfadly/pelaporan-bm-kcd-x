<?php

namespace Database\Seeders;

use App\Models\DistribusiBhp\KodeRekening;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DistribusiBhpImportSeeder extends Seeder
{
    protected string $connection = 'bhp_import';

    protected ?string $sourcePath = null;

    private int $kodeRekeningCount = 0;

    public function setConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function setSourcePath(?string $path): static
    {
        $this->sourcePath = $path;

        return $this;
    }

    public function run(): void
    {
        $this->registerConnection();

        $db = DB::connection($this->connection);

        if (! $this->tableExists($db, 'kode_rekening')) {
            $this->command?->warn('Tabel sumber Distribusi BHP tidak ditemukan; impor dilewati.');

            return;
        }

        DB::transaction(function () use ($db) {
            $this->importKodeRekening($db);
        });

        $this->command?->info(sprintf(
            'Impor BHP selesai: %d kode rekening.',
            $this->kodeRekeningCount,
        ));
    }

    private function registerConnection(): void
    {
        if (config("database.connections.{$this->connection}")) {
            return;
        }

        config()->set("database.connections.{$this->connection}", [
            'driver' => 'sqlite',
            'database' => $this->sourcePath ?? base_path('../sistem-manajemen-aset/prisma/dev.db.backup.db'),
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    private function tableExists($db, string $table): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function sourceTable($db, string $table)
    {
        $query = $db->table($table);

        if ($this->hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function importKodeRekening($db): void
    {
        $this->sourceTable($db, 'kode_rekening')
            ->orderBy('kode')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    SumberDana::dengan($row->sumber_dana ?: SumberDana::BOS, function () use ($row) {
                        KodeRekening::updateOrCreate(
                            ['kode' => $row->kode],
                            [
                                'nama' => $row->nama,
                                'kode_barang' => $row->kode_barang,
                                'uraian_kode_barang' => $row->uraian_kode_barang,
                            ],
                        );
                    });
                    $this->kodeRekeningCount++;
                }
            });
    }
}
