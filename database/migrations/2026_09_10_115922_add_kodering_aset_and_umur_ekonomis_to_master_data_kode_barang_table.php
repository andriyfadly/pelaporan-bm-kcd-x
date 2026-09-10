<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_data_kode_barang', function (Blueprint $table) {
            $table->string('kodering_aset', 100)->nullable()->after('uraian');
            $table->integer('umur_ekonomis')->default(0)->after('jenis_aset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_data_kode_barang', function (Blueprint $table) {
            $table->dropColumn(['kodering_aset', 'umur_ekonomis']);
        });
    }
};
