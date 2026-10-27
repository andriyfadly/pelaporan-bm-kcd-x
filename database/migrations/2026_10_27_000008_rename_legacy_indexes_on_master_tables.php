<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nama index ikut menempel pada tabel lama setelah rename. Kosmetik, tetapi
     * nama yang menyebut "inventaris" pada tabel master menyesatkan pembaca
     * skema. Hanya PostgreSQL yang mendukung rename index.
     */
    private const RENAME = [
        'inventaris_ruang_pkey' => 'master_data_ruang_pkey',
        'inventaris_pegawai_pkey' => 'master_data_pegawai_pkey',
        'inventaris_pegawai_sekolah_id_nama_lengkap_index' => 'master_data_pegawai_sekolah_id_nama_lengkap_index',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::RENAME as $lama => $baru) {
            DB::statement("ALTER INDEX IF EXISTS {$lama} RENAME TO {$baru}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::RENAME as $lama => $baru) {
            DB::statement("ALTER INDEX IF EXISTS {$baru} RENAME TO {$lama}");
        }
    }
};
