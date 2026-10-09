<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus paper_size & orientation dari bhp_{bos|bopd}_settings.
 * Ukuran & orientasi PDF ditentukan hardcode per jenis dokumen di
 * DokumenController, bukan konfigurasi user.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            Schema::table("bhp_{$dana}_settings", function ($table) {
                $table->dropColumn(['paper_size', 'orientation']);
            });
        }
    }

    public function down(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            Schema::table("bhp_{$dana}_settings", function ($table) {
                $table->string('paper_size', 10)->default('a4');
                $table->string('orientation', 20)->default('portrait');
            });
        }
    }
};
