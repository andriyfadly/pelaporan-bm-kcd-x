<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ruang dan pegawai bukan bagian dari domain inventaris: keduanya dipakai
     * lintas modul (penempatan aset, penandatangan dokumen). Rename dipilih
     * daripada drop+create supaya FK di inventaris_unit/inventaris_mutasi
     * tidak perlu dibongkar.
     */
    public function up(): void
    {
        Schema::rename('inventaris_ruang', 'master_data_ruang');
        Schema::rename('inventaris_pegawai', 'master_data_pegawai');

        Schema::table('master_data_ruang', function (Blueprint $table) {
            $table->boolean('aktif')->default(true);
        });

        Schema::table('master_data_pegawai', function (Blueprint $table) {
            $table->boolean('aktif')->default(true);
        });

        // Nama index hanya kosmetik; SQLite tidak mendukung ALTER INDEX.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX inventaris_ruang_sekolah_nama_unique RENAME TO master_data_ruang_sekolah_nama_unique');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX master_data_ruang_sekolah_nama_unique RENAME TO inventaris_ruang_sekolah_nama_unique');
        }

        Schema::table('master_data_pegawai', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });

        Schema::table('master_data_ruang', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });

        Schema::rename('master_data_pegawai', 'inventaris_pegawai');
        Schema::rename('master_data_ruang', 'inventaris_ruang');
    }
};
