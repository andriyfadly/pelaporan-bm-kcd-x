<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pelaporan_bm_acuan', 'pelaporan_bm_spj', 'pelaporan_bm_realisasi'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('tahun')->nullable()->default(2026)->index();
            });
            DB::table($tableName)->whereNull('tahun')->update(['tahun' => 2026]);
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('tahun')->default(2026)->nullable(false)->change();
            });
        }

        Schema::table('pelaporan_bm_kunci_laporan', function (Blueprint $table): void {
            $table->dropUnique(['sekolah_id', 'bulan']);
        });
        DB::table('pelaporan_bm_kunci_laporan')->whereNull('tahun')->update(['tahun' => 2026]);
        Schema::table('pelaporan_bm_kunci_laporan', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tahun')->default(2026)->nullable(false)->change();
            $table->unique(['sekolah_id', 'bulan', 'tahun']);
        });

        Schema::table('pelaporan_bm_acuan', function (Blueprint $table): void {
            $table->index(['sekolah_id', 'bulan', 'tahun']);
        });
        Schema::table('pelaporan_bm_spj', function (Blueprint $table): void {
            $table->index(['sekolah_id', 'bulan_realisasi', 'tahun']);
        });
        Schema::table('pelaporan_bm_realisasi', function (Blueprint $table): void {
            $table->index(['sekolah_id', 'bulan_realisasi', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::table('pelaporan_bm_kunci_laporan', function (Blueprint $table): void {
            $table->dropUnique(['sekolah_id', 'bulan', 'tahun']);
            $table->unique(['sekolah_id', 'bulan']);
        });

        foreach (['pelaporan_bm_acuan', 'pelaporan_bm_spj', 'pelaporan_bm_realisasi'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('tahun');
            });
        }
    }
};
