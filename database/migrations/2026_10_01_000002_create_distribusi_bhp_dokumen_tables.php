<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counter nomor dokumen BHP, diskop per (jenis, sumber dana, tahun, bulan).
        Schema::create('bhp_nomor_sequence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 50);
            $table->enum('sumber_dana', ['BOS', 'BOPD']);
            $table->integer('tahun');
            $table->integer('bulan');
            $table->unsignedInteger('counter')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'sumber_dana', 'tahun', 'bulan']);
        });

        // Dokumen distribusi yang dihasilkan dari approval pengajuan BHP.
        Schema::create('bhp_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bhp_pengajuan_id')->index();
            $table->string('jenis', 50);
            $table->string('nomor', 150);
            $table->date('tanggal_titimangsa')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['bhp_pengajuan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_dokumen');
        Schema::dropIfExists('bhp_nomor_sequence');
    }
};
