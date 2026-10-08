<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histori batch PDF dokumen BHP: satu baris per batch, menyimpan status
     * dan daftar file (beserta error per file) sesuai docs/requirements/reports-and-documents.md.
     */
    public function up(): void
    {
        Schema::create('bhp_batch_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jenis', 50);
            $table->enum('sumber_dana', ['BOS', 'BOPD']);
            $table->foreignUuid('bhp_pengajuan_id')->nullable()->index();
            $table->string('status', 30)->default('pending');
            $table->integer('total')->default(0);
            $table->integer('berhasil')->default(0);
            $table->integer('gagal')->default(0);
            $table->json('files')->nullable();
            $table->text('error_message')->nullable();
            $table->string('path')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhp_batch_dokumen');
    }
};
