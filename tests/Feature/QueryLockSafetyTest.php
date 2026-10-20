<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * PostgreSQL menolak "FOR UPDATE" pada query agregat (SQLSTATE 0A000),
 * sedangkan SQLite membuang klausa lock sehingga suite lokal tetap hijau.
 * Penjaga ini memindai source agar pola agregat + lock tidak kembali masuk.
 */
class QueryLockSafetyTest extends TestCase
{
    public function test_tidak_ada_query_agregat_yang_dikunci_for_update(): void
    {
        $pelanggaran = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $isi = File::get($file->getPathname());

            // Satu chain query = satu statement (berakhir di titik koma).
            foreach (preg_split('/;/', $isi) as $nomorBaris => $statement) {
                if (! str_contains($statement, 'lockForUpdate()')) {
                    continue;
                }

                if (preg_match('/->(sum|count|avg|max|min)\(/', $statement) === 1) {
                    $pelanggaran[] = $file->getRelativePathname().' (statement #'.$nomorBaris.')';
                }
            }
        }

        $this->assertSame([], $pelanggaran, 'Query agregat tidak boleh memakai lockForUpdate(): '
            .implode(', ', $pelanggaran));
    }
}
