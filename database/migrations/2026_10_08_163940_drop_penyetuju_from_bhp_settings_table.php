<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = Schema::hasTable('bhp_bos_settings')
            ? ['bhp_bos_settings', 'bhp_bopd_settings']
            : ['bhp_settings'];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $drop = array_intersect($columns, ['jabatan_penyetuju', 'nama_penyetuju']);
            if ($drop) {
                Schema::table($table, fn ($t) => $t->dropColumn($drop));
            }
        }
    }

    public function down(): void
    {
        $tables = Schema::hasTable('bhp_bos_settings')
            ? ['bhp_bos_settings', 'bhp_bopd_settings']
            : ['bhp_settings'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('jabatan_penyetuju', 255)->nullable();
                $t->string('nama_penyetuju', 255)->nullable();
            });
        }
    }
};
