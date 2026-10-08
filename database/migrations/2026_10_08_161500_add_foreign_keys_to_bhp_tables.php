<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign key relasi BHP. Database sebelumnya menerima orphan record
     * (audit 2026-10-08 temuan #3). Aturan delete:
     * - detail/dokumen ikut parent-nya (cascade);
     * - histori transaksi melindungi master (restrict);
     * - users.divisi_id dilepas saat divisi dihapus (set null).
     */
    public function up(): void
    {
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->foreign('sekolah_id')->references('id')->on('master_data_sekolah')->restrictOnDelete();
        });

        Schema::table('bhp_penerimaan_detail', function (Blueprint $table) {
            $table->foreign('bhp_penerimaan_id')->references('id')->on('bhp_penerimaan')->cascadeOnDelete();
            $table->foreign('barang_id')->references('id')->on('bhp_barang')->restrictOnDelete();
        });

        Schema::table('bhp_pengajuan', function (Blueprint $table) {
            $table->foreign('sekolah_id')->references('id')->on('master_data_sekolah')->restrictOnDelete();
            $table->foreign('divisi_id')->references('id')->on('bhp_divisi')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('bhp_pengajuan_detail', function (Blueprint $table) {
            $table->foreign('bhp_pengajuan_id')->references('id')->on('bhp_pengajuan')->cascadeOnDelete();
            $table->foreign('barang_id')->references('id')->on('bhp_barang')->restrictOnDelete();
        });

        Schema::table('bhp_dokumen', function (Blueprint $table) {
            $table->foreign('bhp_pengajuan_id')->references('id')->on('bhp_pengajuan')->cascadeOnDelete();
        });

        Schema::table('bhp_harga_barang', function (Blueprint $table) {
            $table->foreign('barang_id')->references('id')->on('bhp_barang')->cascadeOnDelete();
        });

        Schema::table('bhp_batch_dokumen', function (Blueprint $table) {
            $table->foreign('bhp_pengajuan_id')->references('id')->on('bhp_pengajuan')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('divisi_id')->references('id')->on('bhp_divisi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['divisi_id']);
        });
        Schema::table('bhp_batch_dokumen', function (Blueprint $table) {
            $table->dropForeign(['bhp_pengajuan_id']);
        });
        Schema::table('bhp_harga_barang', function (Blueprint $table) {
            $table->dropForeign(['barang_id']);
        });
        Schema::table('bhp_dokumen', function (Blueprint $table) {
            $table->dropForeign(['bhp_pengajuan_id']);
        });
        Schema::table('bhp_pengajuan_detail', function (Blueprint $table) {
            $table->dropForeign(['bhp_pengajuan_id']);
            $table->dropForeign(['barang_id']);
        });
        Schema::table('bhp_pengajuan', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id']);
            $table->dropForeign(['divisi_id']);
            $table->dropForeign(['user_id']);
        });
        Schema::table('bhp_penerimaan_detail', function (Blueprint $table) {
            $table->dropForeign(['bhp_penerimaan_id']);
            $table->dropForeign(['barang_id']);
        });
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id']);
        });
    }
};
