<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelaporan_bm_acuan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->index();
            $table->string('satuan_pendidikan', 150)->nullable();
            $table->string('npsn', 20)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('kodering', 255)->nullable();
            $table->string('bku', 100)->nullable();
            $table->text('uraian')->nullable();
            $table->decimal('nominal', 15, 2)->nullable();
            $table->string('bulan', 30)->nullable()->index();
            $table->unsignedInteger('id_acuan_lama')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelaporan_bm_acuan');
    }
};
