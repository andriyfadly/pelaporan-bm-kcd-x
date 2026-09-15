<?php

namespace Database\Seeders;

use App\Models\Master\Sekolah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class KodeSekolahSeeder extends Seeder
{
    public function run(): void
    {
        $sqlPath = storage_path('bm-kcd-x.sql');
        if (! File::exists($sqlPath)) {
            return;
        }

        $sql = File::get($sqlPath);
        if (! preg_match('/INSERT INTO `kode_sekolah`[^V]+VALUES\s*(.+?);/s', $sql, $matches)) {
            return;
        }

        preg_match_all("/\((\d+),\s*(\d+),\s*'([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']+)',\s*(NULL|\d+)\)/", $matches[1], $rows, PREG_SET_ORDER);

        foreach ($rows as $row) {
            Sekolah::updateOrCreate(
                ['id_sekolah_lama' => (int) $row[1]],
                [
                    'no_urut' => (int) $row[2],
                    'nama_sekolah' => trim($row[3]),
                    'kota_kab' => trim($row[4]),
                    'kode_sub_pengguna' => trim($row[5]),
                    'kode_wilayah' => trim($row[6]),
                ]
            );
        }
    }
}
