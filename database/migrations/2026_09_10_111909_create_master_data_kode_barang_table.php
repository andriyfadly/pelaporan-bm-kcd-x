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
        Schema::create('master_data_kode_barang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_barang', 50)->index();
            $table->string('uraian', 255)->index();
            $table->string('jenis_aset', 100)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_standar', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_data_kode_barang');
    }
};
