<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Unique constraint kunci_laporan per sekolah+bulan (updateOrCreate tidak aman tanpa ini)
        Schema::table('pelaporan_bm_kunci_laporan', function (Blueprint $table) {
            $table->unique(['sekolah_id', 'bulan']);
        });

        // 2. Samakan tipe bulan menjadi integer (spj.bulan_realisasi sudah integer)
        // ponytail: raw USING hanya untuk pgsql; sqlite (test) tidak menegakkan tipe kolom
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pelaporan_bm_realisasi ALTER COLUMN bulan_realisasi TYPE integer USING NULLIF(TRIM(bulan_realisasi), \'\')::integer');
            DB::statement('ALTER TABLE pelaporan_bm_acuan ALTER COLUMN bulan TYPE integer USING NULLIF(TRIM(bulan), \'\')::integer');
            DB::statement('ALTER TABLE pelaporan_bm_kunci_laporan ALTER COLUMN bulan TYPE integer USING NULLIF(TRIM(bulan), \'\')::integer');
        }

        // 3. Drop flag is_realisasi (derivable dari keberadaan row realisasi; data lama sudah drift)
        Schema::table('pelaporan_bm_realisasi', function (Blueprint $table) {
            $table->dropColumn('is_realisasi');
        });
        Schema::table('pelaporan_bm_spj', function (Blueprint $table) {
            $table->dropColumn('is_realisasi');
        });
    }

    public function down(): void
    {
        Schema::table('pelaporan_bm_spj', function (Blueprint $table) {
            $table->boolean('is_realisasi')->default(false);
        });
        Schema::table('pelaporan_bm_realisasi', function (Blueprint $table) {
            $table->boolean('is_realisasi')->default(false);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pelaporan_bm_realisasi ALTER COLUMN bulan_realisasi TYPE varchar(50) USING bulan_realisasi::varchar');
            DB::statement('ALTER TABLE pelaporan_bm_acuan ALTER COLUMN bulan TYPE varchar(30) USING bulan::varchar');
            DB::statement('ALTER TABLE pelaporan_bm_kunci_laporan ALTER COLUMN bulan TYPE varchar(10) USING bulan::varchar');
        }
        Schema::table('pelaporan_bm_kunci_laporan', function (Blueprint $table) {
            $table->dropUnique(['sekolah_id', 'bulan']);
        });
    }
};
