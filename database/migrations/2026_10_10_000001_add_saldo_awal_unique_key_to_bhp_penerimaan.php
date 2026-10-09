<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keunikan saldo awal dijaga di aplikasi (SaldoAwalService) untuk pesan
     * error yang ramah, tetapi check-then-insert tetap bisa kalah race saat
     * dua request bersamaan. Partial unique index ini adalah jaring terakhir
     * di database.
     *
     * Kunci alaminya "sekolah + sumber dana + periode + barang", namun
     * barang_id ada di tabel detail sedangkan sekolah/sumber dana/periode di
     * tabel header. Solusinya: satu baris header saldo awal menampung seluruh
     * barang untuk periode tersebut, sehingga kunci cukup
     * (sekolah, sumber dana, periode) dan unik pada level header.
     *
     * `saldo_awal_key` menyatukan ketiganya karena unique index PostgreSQL
     * memperlakukan NULL sebagai nilai berbeda, sehingga sekolah_id NULL
     * (pool pusat admin) tidak pernah dianggap duplikat.
     */
    public function up(): void
    {
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->text('saldo_awal_key')->nullable();
        });

        $driver = DB::connection()->getDriverName();

        // Cast sekolah_id portable: pgsql butuh "::text" karena concat teks +
        // uuid tidak implicit; sqlite/mysql memakai varchar sehingga aman.
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

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bhp_saldo_awal_barang_unique');

        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->dropColumn('saldo_awal_key');
        });
    }
};
