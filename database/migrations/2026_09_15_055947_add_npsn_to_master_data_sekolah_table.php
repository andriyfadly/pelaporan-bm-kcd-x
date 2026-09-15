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
        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->string('npsn', 20)->nullable()->index()->after('nama_sekolah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->dropColumn('npsn');
        });
    }
};
