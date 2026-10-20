<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\SearchesText;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * PostgreSQL LIKE bersifat case-sensitive sedangkan SQLite (engine test) tidak,
 * sehingga bug pencarian huruf kecil tidak pernah tertangkap suite lokal.
 * Penjaga ini mengunci dua hal: kompilasi ILIKE di PostgreSQL, dan tidak adanya
 * pemakaian LIKE mentah di controller.
 */
class CaseInsensitiveSearchTest extends TestCase
{
    private function searcher(): object
    {
        return new class
        {
            use SearchesText;

            public function build(QueryBuilder $query, array $columns, string $term): QueryBuilder
            {
                $this->whereAnyLike($query, $columns, $term);

                return $query;
            }

            public function pattern(string $term): string
            {
                return $this->likePattern($term);
            }
        };
    }

    public function test_where_any_like_memakai_ilike_di_postgres(): void
    {
        $connection = new PostgresConnection(fn () => null, 'verify');

        $sql = $this->searcher()
            ->build($connection->table('master_data_sekolah'), ['nama_sekolah', 'npsn'], 'smkn')
            ->toSql();

        $this->assertStringContainsString('ilike', $sql);
        $this->assertStringNotContainsString(' like ', $sql);
    }

    public function test_like_pattern_meng_escape_wildcard_dan_membungkus_persen(): void
    {
        $this->assertSame('%smkn%', $this->searcher()->pattern('  smkn  '));
        $this->assertSame('%\\%%', $this->searcher()->pattern('%'));
        $this->assertSame('%\\_%', $this->searcher()->pattern('_'));
        $this->assertSame('%\\\\%', $this->searcher()->pattern('\\'));
    }

    public function test_tidak_ada_like_mentah_di_controller(): void
    {
        $pelanggaran = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            $barisList = explode("\n", File::get($file->getPathname()));

            foreach ($barisList as $nomor => $baris) {
                if (str_contains($baris, '//') || str_contains($baris, '*')) {
                    continue;
                }

                // whereColumn(... 'like' ...) membandingkan dua KOLOM (prefix
                // hierarki kode barang), bukan input user. Tidak ada padanan
                // whereColumnLike di Laravel dan nilainya angka/titik, jadi
                // case-sensitivity tidak relevan di sini.
                if (str_contains($baris, 'whereColumn')) {
                    continue;
                }

                if (preg_match("/'like'/i", $baris) === 1) {
                    $pelanggaran[] = $file->getRelativePathname().':'.($nomor + 1).' → '.trim($baris);
                }

                if (preg_match('/LOWER\([^)]*\)\s*LIKE/i', $baris) === 1) {
                    $pelanggaran[] = $file->getRelativePathname().':'.($nomor + 1).' → '.trim($baris);
                }
            }
        }

        $this->assertSame([], $pelanggaran, "Pakai whereLike()/SearchesText, bukan LIKE mentah:\n".implode("\n", $pelanggaran));
    }
}
