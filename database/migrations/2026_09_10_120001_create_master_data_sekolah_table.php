<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_sekolah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('no_urut')->nullable();
            $table->string('nama_sekolah', 150);
            $table->string('kota_kab', 100);
            $table->string('kode_sub_pengguna', 20)->nullable();
            $table->string('kode_wilayah', 10)->nullable();
            $table->unsignedInteger('id_sekolah_lama')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_sekolah');
    }
};
