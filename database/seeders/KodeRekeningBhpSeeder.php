<?php

namespace Database\Seeders;

use App\Models\DistribusiBhp\KodeRekening;
use App\Support\DistribusiBhp\KodeBarang;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KodeRekeningBhpSeeder extends Seeder
{
    public function run(): void
    {
        $files = [
            SumberDana::BOS => storage_path('app/kode-rekening-bos.xlsx'),
            SumberDana::BOPD => storage_path('app/kode-rekening-bopd.xlsx'),
        ];

        $total = 0;

        foreach ($files as $dana => $path) {
            if (! file_exists($path)) {
                $this->command?->warn("File tidak ditemukan: {$path}");

                continue;
            }

            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->getRowIterator(2);

            SumberDana::dengan($dana, function () use ($rows, &$total) {
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $cells[] = trim((string) $cell->getValue());
                    }

                    $kode = $cells[0] ?? '';
                    $nama = $cells[1] ?? '';

                    if ($kode === '' || $nama === '') {
                        continue;
                    }

                    // kode_barang wajib terisi: NuspService menolaknya bila
                    // kosong, sehingga Tambah Barang mustahil dilakukan dari UI
                    // pada database yang baru di-seed.
                    $klasifikasi = KodeBarang::untukNamaRekening($nama);

                    KodeRekening::updateOrCreate(
                        ['kode' => $kode],
                        [
                            'nama' => $nama,
                            'kode_barang' => $klasifikasi['kode'],
                            'uraian_kode_barang' => $klasifikasi['uraian'],
                        ],
                    );
                    $total++;
                }
            });
        }

        $this->command?->info("Impor kode rekening selesai: {$total} baris.");
    }
}
