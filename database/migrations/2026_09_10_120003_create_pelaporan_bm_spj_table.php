<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelaporan_bm_spj', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->nullable()->index();
            $table->foreignUuid('acuan_id')->nullable()->index();
            $table->string('kategori', 50)->nullable();
            $table->string('no_sp2d', 100)->nullable();
            $table->string('sumber_perolehan', 100)->nullable();
            $table->integer('bulan_realisasi')->nullable();
            $table->string('no_spk', 150)->index();
            $table->string('ba_no', 150)->nullable();
            $table->date('ba_tgl')->nullable();
            $table->string('kode_barang', 50);
            $table->string('nama_barang', 255);
            $table->string('jenis_aset', 100);
            $table->string('merk_tipe', 255)->nullable();
            $table->string('no_sertifikat', 150)->nullable();
            $table->string('ukuran_bangunan', 150)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->decimal('volume', 10, 2);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('nilai_perolehan', 15, 2);
            $table->boolean('is_realisasi')->default(false);
            $table->unsignedInteger('id_spj_lama')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelaporan_bm_spj');
    }
};
