<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelaporan_bm_kunci_laporan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->index();
            $table->string('bulan', 10)->nullable()->index();
            $table->integer('tahun')->nullable()->index();
            $table->boolean('status_kunci')->default(false);
            $table->timestamp('dikunci_pada')->nullable();
            $table->foreignUuid('dikunci_oleh')->nullable()->index();
            $table->string('status_kirim', 50)->default('draft')->index();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelaporan_bm_kunci_laporan');
    }
};
