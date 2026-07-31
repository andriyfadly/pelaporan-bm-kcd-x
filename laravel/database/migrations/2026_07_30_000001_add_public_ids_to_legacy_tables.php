<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'kode_sekolah',
        'users',
        'data_barang_acuan',
        'laporan_realisasi',
        'master_barang_sekolah',
        'realisasi_barang_sekolah',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->uuid('public_id')->nullable()->unique("{$table}_public_id_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique("{$table}_public_id_unique");
                $blueprint->dropColumn('public_id');
            });
        }
    }
};
