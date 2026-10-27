<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format nomor per jenis dokumen BHP, bukan satu format untuk semua.
 * Default tetap isi `format_nomor` — kolom baru nullable tanpa default.
 */
return new class extends Migration
{
    private const KOLOM = [
        'format_nomor_nota_permintaan',
        'format_nomor_surat_permintaan',
        'format_nomor_surat_penyaluran',
        'format_nomor_bast',
        'format_nomor_bap',
        'format_nomor_stok_opname',
        'format_nomor_buku_pengeluaran',
        'format_nomor_buku_penyaluran',
    ];

    public function up(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            Schema::table("bhp_{$dana}_settings", function (Blueprint $table) {
                foreach (self::KOLOM as $kolom) {
                    $table->string($kolom, 200)->nullable()->after('format_nomor');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            Schema::table("bhp_{$dana}_settings", function (Blueprint $table) {
                foreach (self::KOLOM as $kolom) {
                    $table->dropColumn($kolom);
                }
            });
        }
    }
};
