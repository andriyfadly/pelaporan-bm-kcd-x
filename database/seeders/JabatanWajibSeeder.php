<?php

namespace Database\Seeders;

use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use Illuminate\Database\Seeder;

/**
 * Tiga jabatan penandatangan dokumen wajib ada di setiap sekolah. Aman
 * dijalankan berulang: yang sudah ada tidak diubah, yang terhapus dipulihkan.
 */
class JabatanWajibSeeder extends Seeder
{
    public function run(): void
    {
        $total = 0;

        Sekolah::query()->select('id')->chunkById(200, function ($sekolah) use (&$total) {
            foreach ($sekolah as $item) {
                Jabatan::pastikanWajib($item->id);
                $total++;
            }
        });

        $this->command?->info("Jabatan wajib siap untuk {$total} sekolah.");
    }
}
