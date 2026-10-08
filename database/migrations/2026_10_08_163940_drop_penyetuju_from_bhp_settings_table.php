<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penandatangan dokumen BHP kini berasal dari master pejabat sekolah
     * (pejabat_sekolah) dengan snapshot di payload dokumen. Kolom
     * penyetuju global pada settings tidak lagi dipakai.
     */
    public function up(): void
    {
        Schema::table('bhp_settings', function (Blueprint $table) {
            $table->dropColumn(['jabatan_penyetuju', 'nama_penyetuju']);
        });
    }

    public function down(): void
    {
        Schema::table('bhp_settings', function (Blueprint $table) {
            $table->string('jabatan_penyetuju', 255)->nullable();
            $table->string('nama_penyetuju', 255)->nullable();
        });
    }
};
