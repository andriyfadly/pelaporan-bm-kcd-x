<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom yang dibutuhkan dokumen acuan, ditaruh pada tabel yang sudah jadi
     * rumahnya:
     *
     * - `master_data_sekolah.kode_pos`      → baris "KOTA - kode pos" pada kop
     * - `master_data_sekolah.kode_bast`     → kode klasifikasi penomoran BAST
     * - `master_data_sekolah.kode_pengembalian` → kode klasifikasi BA Pengembalian
     * - `master_data_sekolah.kode_fakta`    → kode klasifikasi Pakta Integritas
     * - `inventaris_unit.bahan`             → kolom BAHAN pada KIR/Buku Inventaris
     */
    public function up(): void
    {
        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->string('bahan', 100)->nullable()->after('merk_tipe');
        });

        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->string('kode_pos', 10)->nullable()->after('kota_kab');
            $table->string('kode_bast', 100)->nullable()->after('kode_pos');
            $table->string('kode_pengembalian', 100)->nullable()->after('kode_bast');
            $table->string('kode_fakta', 100)->nullable()->after('kode_pengembalian');
        });
    }

    public function down(): void
    {
        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->dropColumn(['kode_pos', 'kode_bast', 'kode_pengembalian', 'kode_fakta']);
        });

        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->dropColumn('bahan');
        });
    }
};
