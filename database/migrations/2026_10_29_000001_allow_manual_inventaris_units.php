<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit inventaris tidak lagi hanya lahir dari sinkronisasi realisasi:
     * operator sekolah boleh mendaftarkan barang sendiri (manual/import),
     * seperti pada aplikasi acuan. Karena itu sumber realisasi menjadi opsional
     * dan `asal_perolehan` disimpan langsung di unit.
     */
    public function up(): void
    {
        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->uuid('realisasi_id')->nullable()->change();
            $table->unsignedInteger('unit_index')->nullable()->change();
            $table->string('asal_perolehan', 150)->nullable()->after('nilai_perolehan_unit');
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->dropColumn('asal_perolehan');
        });
    }
};
