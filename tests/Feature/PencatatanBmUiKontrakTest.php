<?php

namespace Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Kontrak UI halaman Pencatatan BM yang hanya terlihat di source klien:
 *
 * 1. Tabel lebar (5+ kolom) wajib punya daftar kartu `md:hidden`; kalau hanya
 *    di-scroll horizontal, kolom aksi di ujung kanan tidak terjangkau di ponsel.
 * 2. Aksi destruktif wajib lewat `ConfirmDialog`, bukan `confirm()` browser.
 * 3. Dialog wajib memakai komponen `Modal` bersama, bukan overlay `fixed inset-0`
 *    buatan sendiri (focus trap/ESC/scroll lock hanya dijamin Modal).
 * 4. Halaman yang punya aksi tulis/cetak menerima boolean permission dari
 *    controller dan memakainya untuk menyembunyikan/menonaktifkan aksi.
 */
class PencatatanBmUiKontrakTest extends TestCase
{
    private const BASIS = 'resources/js/Pages/PencatatanBm/';

    /** @return array<string, array{0: string}> */
    public static function halamanTabelLebar(): array
    {
        return [
            'inventaris' => ['Inventaris/Index.tsx'],
            'penyaluran' => ['Penyaluran/Index.tsx'],
            'pengembalian' => ['Pengembalian/Index.tsx'],
            'sinkronisasi' => ['Sinkronisasi/Index.tsx'],
            'berita acara' => ['BeritaAcara/Index.tsx'],
        ];
    }

    #[DataProvider('halamanTabelLebar')]
    public function test_tabel_lebar_punya_daftar_mobile(string $berkas): void
    {
        $isi = $this->baca($berkas);

        $this->assertStringContainsString('md:hidden', $isi, "{$berkas} tidak punya daftar kartu untuk layar kecil.");
        $this->assertStringContainsString('hidden md:block', $isi, "{$berkas} masih menampilkan tabel penuh di layar kecil.");
    }

    /** @return array<string, array{0: string}> */
    public static function halamanBerpaginator(): array
    {
        return [
            'inventaris' => ['Inventaris/Index.tsx'],
            'mutasi' => ['Mutasi/Index.tsx'],
        ];
    }

    #[DataProvider('halamanBerpaginator')]
    public function test_halaman_berpaginator_merender_pagination(string $berkas): void
    {
        $isi = $this->baca($berkas);

        $this->assertStringContainsString('@/Components/Pagination', $isi, "{$berkas} tidak mengimpor Pagination.");
        $this->assertStringContainsString('<Pagination', $isi, "{$berkas} tidak merender Pagination.");
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function halamanDenganPermission(): array
    {
        return [
            'inventaris kelola' => ['Inventaris/Index.tsx', 'canKelola'],
            'inventaris cetak' => ['Inventaris/Index.tsx', 'canCetak'],
            'penyaluran mutasi' => ['Penyaluran/Index.tsx', 'canMutasi'],
            'penyaluran cetak' => ['Penyaluran/Index.tsx', 'canCetak'],
            'pengembalian proses' => ['Pengembalian/Index.tsx', 'canProsesPengembalian'],
            'mutasi' => ['Mutasi/Index.tsx', 'canMutasi'],
            'dokumen' => ['Dokumen/Index.tsx', 'canCetak'],
            'label' => ['Label/Index.tsx', 'canCetak'],
            'berita acara' => ['BeritaAcara/Index.tsx', 'canCetak'],
            'pengguna' => ['Pengguna/Index.tsx', 'canCetak'],
            'sinkronisasi' => ['Sinkronisasi/Index.tsx', 'canSinkronisasi'],
        ];
    }

    #[DataProvider('halamanDenganPermission')]
    public function test_halaman_memakai_permission_prop(string $berkas, string $prop): void
    {
        $isi = $this->baca($berkas);

        $this->assertMatchesRegularExpression(
            '/interface Props\b[\s\S]*?\b'.preg_quote($prop, '/').'\s*:\s*boolean/',
            $isi,
            "{$berkas} tidak mendeklarasikan prop permission `{$prop}`.",
        );
        $this->assertStringContainsString(
            $prop,
            $isi,
            "{$berkas} tidak memakai `{$prop}` untuk mengatur aksinya.",
        );
    }

    public function test_tidak_ada_confirm_bawaan_browser(): void
    {
        foreach ($this->berkasTsx() as $path => $isi) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![.\w])confirm\(/',
                $isi,
                basename($path).' memakai confirm() bawaan browser; gunakan ConfirmDialog.',
            );
        }
    }

    public function test_tidak_ada_overlay_modal_buatan_sendiri(): void
    {
        foreach ($this->berkasTsx() as $path => $isi) {
            $this->assertStringNotContainsString(
                'fixed inset-0',
                $isi,
                basename($path).' memakai overlay sendiri; gunakan komponen Modal bersama.',
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function berkasTsx(): array
    {
        $hasil = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path(self::BASIS), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'tsx') {
                $hasil[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        $this->assertNotEmpty($hasil, 'Tidak ada halaman Pencatatan BM yang terbaca.');

        return $hasil;
    }

    private function baca(string $berkas): string
    {
        $path = base_path(self::BASIS.$berkas);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
