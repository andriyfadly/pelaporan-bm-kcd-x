<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan BHP kini memfilter per periode (bulan) pada tanggal bisnis, bukan
 * lagi membaca seluruh tabel. Tambahkan index komposit yang menopang pola
 * query `sekolah_id + tanggal` tanpa mengubah data.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $dana = ['bos', 'bopd'];

    public function up(): void
    {
        foreach ($this->dana as $dana) {
            Schema::table("bhp_{$dana}_pengajuan", function (Blueprint $table) use ($dana) {
                $table->index(['sekolah_id', 'tanggal'], "bhp_{$dana}_pengajuan_sekolah_tanggal_idx");
            });

            Schema::table("bhp_{$dana}_penerimaan", function (Blueprint $table) use ($dana) {
                $table->index(['sekolah_id', 'tanggal_bast'], "bhp_{$dana}_penerimaan_sekolah_bast_idx");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->dana as $dana) {
            Schema::table("bhp_{$dana}_pengajuan", function (Blueprint $table) use ($dana) {
                $table->dropIndex("bhp_{$dana}_pengajuan_sekolah_tanggal_idx");
            });

            Schema::table("bhp_{$dana}_penerimaan", function (Blueprint $table) use ($dana) {
                $table->dropIndex("bhp_{$dana}_penerimaan_sekolah_bast_idx");
            });
        }
    }
};
