<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pelaporan_bm_acuan', function (Blueprint $table) {
            $table->dropColumn(['satuan_pendidikan', 'npsn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelaporan_bm_acuan', function (Blueprint $table) {
            $table->string('satuan_pendidikan', 150)->nullable();
            $table->string('npsn', 20)->nullable();
        });
    }
};
