<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil kontak sekolah untuk kop/pengirim dokumen (generic, diisi
     * operator sekolah sendiri via halaman Profil Sekolah).
     */
    public function up(): void
    {
        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->string('alamat', 500)->nullable()->after('kota_kab');
            $table->string('telepon', 30)->nullable()->after('alamat');
            $table->string('website', 255)->nullable()->after('telepon');
            $table->string('email', 255)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('master_data_sekolah', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'telepon', 'website', 'email']);
        });
    }
};
