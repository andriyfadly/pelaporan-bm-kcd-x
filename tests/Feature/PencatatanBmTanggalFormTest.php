<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga regresi tanggal default form Pencatatan BM.
 *
 * `new Date().toISOString()` memakai UTC: di WIB sebelum 07:00 tanggalnya
 * masih kemarin, sehingga form mengirim tanggal perolehan/pengembalian yang
 * salah satu hari. Semua halaman wajib memakai `tanggalHariIni()`.
 *
 * Diperiksa dari source karena ini murni perilaku browser (zona waktu klien)
 * yang tidak terlihat dari test HTTP.
 */
class PencatatanBmTanggalFormTest extends TestCase
{
    public function test_tidak_ada_new_date_toisostring_di_form_pencatatan_bm(): void
    {
        $basis = base_path('resources/js/Pages/PencatatanBm');

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basis, \FilesystemIterator::SKIP_DOTS),
        );

        $pelanggaran = [];

        foreach ($berkas as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['tsx', 'ts'], true)) {
                continue;
            }

            $isi = (string) file_get_contents($file->getPathname());

            if (preg_match('/new Date\(\)\.toISOString\(\)\.slice\(\s*0\s*,\s*10\s*\)/', $isi) === 1) {
                $pelanggaran[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            'Form Pencatatan BM memakai tanggal UTC; ganti dengan tanggalHariIni() dari @/Utils/format.',
        );
    }

    public function test_halaman_berform_tanggal_memakai_helper_lokal(): void
    {
        foreach ([
            'Pengembalian/Index.tsx',
            'Pengguna/Index.tsx',
            'Dokumen/Index.tsx',
            'Penyaluran/Index.tsx',
            'Inventaris/Index.tsx',
            'Inventaris/Show.tsx',
        ] as $halaman) {
            $isi = (string) file_get_contents(base_path("resources/js/Pages/PencatatanBm/{$halaman}"));

            $this->assertStringContainsString(
                'tanggalHariIni',
                $isi,
                "{$halaman} tidak memakai tanggalHariIni() untuk nilai tanggal default.",
            );
        }
    }
}
