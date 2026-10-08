<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PeranDanHakAksesSeeder::class,
            KodeSekolahSeeder::class,
            UserSeeder::class,
        ]);

        // Impor data legacy Distribusi BHP (master + penerimaan) bila DB sumber
        // tersedia. Aman diulang; lihat DistribusiBhpImportSeeder untuk detail.
        $this->call(DistribusiBhpImportSeeder::class);
    }
}
