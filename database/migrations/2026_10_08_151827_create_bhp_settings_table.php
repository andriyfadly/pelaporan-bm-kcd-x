<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings BHP: format nomor dokumen, preferensi PDF, dan penandatangan.
     * PRD: "Format nomor configurable melalui settings BHP" (docs/requirements/distribusi-bhp-prd.md bagian 8).
     */
    public function up(): void
    {
        Schema::create('bhp_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('format_nomor', 200)
                ->default('{urut:3}/BHP/{kode}/{sumber_dana}/{bulan_romawi}/{tahun}');
            $table->string('paper_size', 10)->default('a4');
            $table->string('orientation', 20)->default('portrait');
            $table->string('jabatan_penyetuju', 255)->nullable();
            $table->string('nama_penyetuju', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_settings');
    }
};
