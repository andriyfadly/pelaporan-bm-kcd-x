<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaris_dokumen', function (Blueprint $table): void {
            // Pembatalan nomor BA tidak menghapus dokumen; hanya tanggalnya dicatat.
            $table->timestamp('nomor_dibatalkan_pada')->nullable()->after('nomor');
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_dokumen', function (Blueprint $table): void {
            $table->dropColumn('nomor_dibatalkan_pada');
        });
    }
};
