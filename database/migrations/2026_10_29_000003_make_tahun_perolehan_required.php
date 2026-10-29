<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `tahun_perolehan` ikut menentukan keunikan register, jadi tidak boleh
     * NULL: di PostgreSQL/MySQL, NULL dianggap berbeda satu sama lain sehingga
     * dua unit register '001' tanpa tahun akan lolos dari unique index.
     *
     * Baris lama diisi dari tanggal perolehan, lalu jatuh ke tahun dibuatnya.
     */
    public function up(): void
    {
        $kosong = DB::table('inventaris_unit')->whereNull('tahun_perolehan');

        if ($kosong->exists()) {
            foreach ($kosong->get(['id', 'tanggal_perolehan', 'created_at']) as $unit) {
                $sumber = (string) ($unit->tanggal_perolehan ?? $unit->created_at ?? now());

                DB::table('inventaris_unit')->where('id', $unit->id)->update([
                    'tahun_perolehan' => (int) substr($sumber, 0, 4),
                ]);
            }
        }

        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_perolehan')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_unit', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_perolehan')->nullable()->change();
        });
    }
};
