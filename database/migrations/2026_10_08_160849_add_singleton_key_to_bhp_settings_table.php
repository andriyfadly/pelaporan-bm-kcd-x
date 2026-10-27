<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton guard untuk bhp_settings: kolom is_singleton dengan unique
     * partial index (hanya boleh satu baris bernilai true). Mencegah race
     * condition firstOrCreate menghasilkan dua baris settings.
     */
    public function up(): void
    {
        Schema::table('bhp_settings', function (Blueprint $table) {
            $table->boolean('is_singleton')->default(true);
        });

        DB::statement('CREATE UNIQUE INDEX bhp_settings_singleton_unique ON bhp_settings (is_singleton) WHERE is_singleton');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bhp_settings_singleton_unique');
        Schema::table('bhp_settings', function (Blueprint $table) {
            $table->dropColumn('is_singleton');
        });
    }
};
