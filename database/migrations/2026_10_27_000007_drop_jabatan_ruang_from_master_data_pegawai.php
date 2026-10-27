<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `jabatan` dan `ruang` di master pegawai adalah sisa model lama: jabatan
     * kini berasal dari riwayat penugasan, penempatan dari master ruang.
     * Dokumen sudah memakai snapshot dari kedua sumber itu.
     */
    public function up(): void
    {
        Schema::table('master_data_pegawai', function (Blueprint $table) {
            $table->dropColumn(['jabatan', 'ruang']);
        });
    }

    public function down(): void
    {
        Schema::table('master_data_pegawai', function (Blueprint $table) {
            $table->string('jabatan', 150)->nullable();
            $table->string('ruang', 100)->nullable();
        });
    }
};
