<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hilangkan konsep "pool pusat" untuk penerimaan/saldo awal BHP.
     *
     * Sebelumnya bhp_penerimaan.sekolah_id boleh NULL dan baris NULL dianggap
     * stok bersama semua sekolah. Karena stok sekarang selalu milik satu
     * sekolah, kolom sekolah_id WAJIB terisi dan keunikan saldo awal dijaga
     * langsung oleh unique index (sekolah_id, sumber_dana, periode_tahun)
     * untuk baris is_saldo_awal — menggantikan kolom bantu `saldo_awal_key`.
     */
    public function up(): void
    {
        // Penerimaan/saldo awal tanpa sekolah (pool pusat lama) dihapus beserta
        // detailnya; stok tanpa pemilik tidak lagi punya makna di domain ini.
        $orphanIds = DB::table('bhp_penerimaan')->whereNull('sekolah_id')->pluck('id')->all();

        if ($orphanIds !== []) {
            DB::table('bhp_penerimaan_detail')->whereIn('bhp_penerimaan_id', $orphanIds)->delete();
            DB::table('bhp_penerimaan')->whereIn('id', $orphanIds)->delete();
        }

        DB::statement('DROP INDEX IF EXISTS bhp_saldo_awal_barang_unique');

        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->dropColumn('saldo_awal_key');
        });

        DB::statement('CREATE UNIQUE INDEX bhp_saldo_awal_sekolah_dana_periode_unique ON bhp_penerimaan (sekolah_id, sumber_dana, periode_tahun) WHERE is_saldo_awal = true');

        // Sekolah tidak lagi opsional untuk penerimaan.
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->uuid('sekolah_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bhp_saldo_awal_sekolah_dana_periode_unique');

        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->uuid('sekolah_id')->nullable()->change();
            $table->text('saldo_awal_key')->nullable();
        });

        $driver = DB::connection()->getDriverName();
        $sekolah = $driver === 'pgsql' ? 'sekolah_id::text' : 'sekolah_id';

        DB::statement(<<<SQL
            UPDATE bhp_penerimaan
            SET saldo_awal_key = coalesce({$sekolah}, '') || '|' || sumber_dana || '|' || periode_tahun
            WHERE is_saldo_awal = true AND periode_tahun IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX bhp_saldo_awal_barang_unique
            ON bhp_penerimaan (saldo_awal_key)
            WHERE is_saldo_awal = true AND saldo_awal_key IS NOT NULL
        SQL);
    }
};
