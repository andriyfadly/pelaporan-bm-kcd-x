<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaris_ruang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->string('nama_ruang', 150);
            $table->string('kode', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Nama ruang unik per sekolah tanpa memandang huruf besar/kecil.
        DB::statement('CREATE UNIQUE INDEX inventaris_ruang_sekolah_nama_unique ON inventaris_ruang (sekolah_id, lower(nama_ruang))');

        Schema::create('inventaris_pegawai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sekolah_id')->constrained('master_data_sekolah')->restrictOnDelete();
            $table->string('nama_lengkap', 150);
            $table->string('nip', 30)->nullable();
            $table->string('pangkat_golongan', 50)->nullable();
            $table->string('jabatan', 150)->nullable();
            $table->string('ruang', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sekolah_id', 'nama_lengkap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaris_pegawai');
        Schema::dropIfExists('inventaris_ruang');
    }
};
