<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `created_by` pada tabel batch dokumen BHP dibuat dengan foreignId (bigint),
 * padahal id user berupa UUID. Setiap kali batch PDF dibuat, insert gagal
 * dengan "invalid input syntax for type bigint" — tombol "Unduh Batch PDF"
 * selalu berakhir 500.
 *
 * Tabel masih kosong pada semua lingkungan, jadi kolomnya bisa diubah langsung
 * tanpa migrasi data.
 */
return new class extends Migration
{
    private const TABEL = ['bhp_bos_batch_dokumen', 'bhp_bopd_batch_dokumen'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table): void {
                $table->dropColumn('created_by');
            });

            Schema::table($tabel, function (Blueprint $table): void {
                $table->foreignUuid('created_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table): void {
                $table->dropColumn('created_by');
            });

            Schema::table($tabel, function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable();
            });
        }
    }
};
