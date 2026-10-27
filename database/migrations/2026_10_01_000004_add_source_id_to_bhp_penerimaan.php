<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom source_id pada bhp_penerimaan untuk menyimpan id asli
     * (legacy) dari database sumber distribusi BHP. Dipakai seeder import agar
     * bersifat idempotent tanpa bergantung pada no_bast yang tidak unik.
     */
    public function up(): void
    {
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->string('source_id', 100)->nullable()->after('id');
            $table->unique('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('bhp_penerimaan', function (Blueprint $table) {
            $table->dropUnique(['source_id']);
            $table->dropColumn('source_id');
        });
    }
};
