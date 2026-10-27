<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Split bhp_settings menjadi bhp_bos_settings & bhp_bopd_settings.
 * Data existing diduplikasi ke masing-masing tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bhp_settings RENAME TO bhp_bos_settings');

        // Buat bhp_bopd_settings dengan struktur identik.
        Schema::create('bhp_bopd_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('format_nomor', 200)
                ->default('{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}');
            $table->boolean('is_singleton')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX bhp_bopd_settings_singleton_unique ON bhp_bopd_settings (is_singleton) WHERE is_singleton');

        // Copy data dari BOS ke BOPD — pakai uuid() untuk kompatibilitas SQLite & PgSQL.
        $row = DB::table('bhp_bos_settings')->first(['format_nomor', 'created_at', 'updated_at']);
        if ($row) {
            DB::table('bhp_bopd_settings')->insert([
                'id' => Str::uuid()->toString(),
                'format_nomor' => $row->format_nomor,
                'is_singleton' => true,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS bhp_bopd_settings');
        DB::statement('ALTER TABLE bhp_bos_settings RENAME TO bhp_settings');
    }
};
