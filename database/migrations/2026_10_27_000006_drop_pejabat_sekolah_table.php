<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pejabat_sekolah adalah sumber data pejabat yang berdiri sendiri. Semua
     * pembacanya kini memakai master_data_pegawai + master_data_jabatan +
     * master_data_penugasan_pegawai, jadi tabel ini dihapus. Guard menolak
     * drop selama masih ada baris: baris pejabat tidak punya padanan otomatis
     * di model baru dan menghapusnya akan menghilangkan data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pejabat_sekolah')) {
            return;
        }

        $jumlah = DB::table('pejabat_sekolah')->count();

        if ($jumlah > 0) {
            throw new RuntimeException(
                "Tabel pejabat_sekolah masih berisi {$jumlah} baris. Pindahkan ke master_data_pegawai + ".
                'master_data_penugasan_pegawai secara manual sebelum menjalankan migrasi ini.'
            );
        }

        Schema::drop('pejabat_sekolah');
    }

    public function down(): void
    {
        // Tidak dipulihkan: skema dan data lama sengaja tidak dihidupkan lagi.
    }
};
