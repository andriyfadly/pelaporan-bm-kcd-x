<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kontrak UI halaman Distribusi BHP yang tidak bisa ditangkap test HTTP:
 *
 * 1. Tabel yang menerima paginator wajib merender `<Pagination>` — tanpa itu
 *    25–50 baris pertama tampil dan sisanya tidak bisa dijangkau sama sekali.
 * 2. Aksi destruktif wajib lewat `ConfirmDialog`, bukan `confirm()` bawaan
 *    browser (tidak bisa di-style, tidak bisa diuji, dan diblokir sebagian
 *    browser).
 * 3. Halaman per sumber dana wajib memakai prefix `/distribusi-bhp/{dana}/...`
 *    pada URL mutasinya. URL flat default ke BOS, sehingga aksi di halaman
 *    BOPD tidak pernah mengenai record-nya.
 *
 * Diperiksa dari source karena semuanya murni sisi klien.
 */
class BhpUiKontrakTest extends TestCase
{
    private const BASIS = 'resources/js/Pages/DistribusiBhp/';

    /** Halaman yang menerima paginator dan wajib menampilkan navigasinya. */
    public static function halamanBerpaginator(): array
    {
        return [
            'pengajuan' => ['Pengajuan/Index.tsx'],
            'review pengajuan' => ['Review/Index.tsx'],
            'penerimaan' => ['Penerimaan/Index.tsx'],
            'lunas bayar' => ['Penerimaan/LunasBayar.tsx'],
            'kode rekening' => ['Master/KodeRekening/Index.tsx'],
            'penyedia' => ['Master/Penyedia/Index.tsx'],
            'daftar dokumen' => ['Laporan/DokumenList.tsx'],
        ];
    }

    #[DataProvider('halamanBerpaginator')]
    public function test_halaman_berpaginator_merender_pagination(string $berkas): void
    {
        $isi = $this->baca($berkas);

        $this->assertStringContainsString(
            '@/Components/Pagination',
            $isi,
            "{$berkas} menerima data terpaginator tetapi tidak mengimpor komponen Pagination.",
        );
        $this->assertStringContainsString(
            '<Pagination',
            $isi,
            "{$berkas} mengimpor Pagination tetapi tidak merendernya.",
        );
    }

    /**
     * Halaman per sumber dana: URL mutasi (post/put/patch/delete) tidak boleh
     * memakai prefix `/distribusi-bhp/` tanpa segmen `bos|bopd` atau
     * `sumber_dana`. Daftar berkas di bawah semuanya berada di bawah grup
     * `sumber-dana-bhp`.
     */
    public static function halamanPerSumberDana(): array
    {
        return [
            'pengajuan' => ['Pengajuan/Index.tsx'],
            'review pengajuan' => ['Review/Index.tsx'],
            'penerimaan' => ['Penerimaan/Index.tsx'],
            'lunas bayar' => ['Penerimaan/LunasBayar.tsx'],
            'saldo awal' => ['SaldoAwal/Index.tsx'],
            'barang' => ['Master/Barang/Index.tsx'],
            'kelola harga' => ['Master/Barang/KelolaHarga.tsx'],
            'kode rekening' => ['Master/KodeRekening/Index.tsx'],
            'dokumen' => ['Dokumen/Show.tsx'],
            'laporan' => ['Laporan/Index.tsx'],
            'buku pengeluaran' => ['Laporan/BukuPengeluaran.tsx'],
            'buku penerimaan' => ['Laporan/BukuPenerimaan.tsx'],
            'kartu persediaan' => ['Laporan/KartuPersediaan.tsx'],
            'daftar dokumen' => ['Laporan/DokumenList.tsx'],
        ];
    }

    /**
     * Rute yang HANYA terdaftar flat (tanpa pasangan `bos`/`bopd`) karena
     * sumber dananya bersama atau dikirim di body. Untuk rute ini URL flat
     * memang benar.
     */
    private const RUTE_FLAT_SAJA = [
        '/distribusi-bhp/settings',
        '/distribusi-bhp/divisi/',
        '/distribusi-bhp/penyedia/',
        '/distribusi-bhp/master/kode-rekening',
    ];

    #[DataProvider('halamanPerSumberDana')]
    public function test_url_mutasi_membawa_sumber_dana(string $berkas): void
    {
        $isi = $this->baca($berkas);

        // Semua kemunculan `/distribusi-bhp/` yang TIDAK diikuti prefix
        // dinamis (`${...}`) harus menyebut `bos`/`bopd` atau sumber_dana.
        preg_match_all('#/distribusi-bhp/([^`\'"\s]*)#', $isi, $cocok);

        foreach ($cocok[1] as $sisa) {
            if ($sisa === '' || str_starts_with($sisa, '$')) {
                continue; // dibangun dari variabel prefix
            }

            foreach (self::RUTE_FLAT_SAJA as $flat) {
                if (str_contains('/distribusi-bhp/'.$sisa, $flat)) {
                    continue 2;
                }
            }

            $this->assertMatchesRegularExpression(
                '#^(bos|bopd)/|sumber_dana=#',
                $sisa,
                "{$berkas} membangun URL flat `/distribusi-bhp/{$sisa}` — default-nya BOS, sehingga halaman BOPD akan mengenai tabel yang salah.",
            );
        }
    }

    public function test_kode_rekening_mengirim_sumber_dana_di_body(): void
    {
        $isi = $this->baca('Master/KodeRekening/Index.tsx');

        // Rute kode-rekening memang hanya flat (tabelnya per dana), jadi sumber
        // dana HARUS ikut di body. Kalau hilang, semua tulisan jatuh ke BOS.
        $this->assertStringContainsString(
            'sumber_dana: sumberDana',
            $isi,
            'Form tambah kode rekening tidak mengirim sumber_dana.',
        );
        $this->assertStringContainsString(
            'sumber_dana: sumberDana, assignments: vals',
            $isi,
            'Batch assign kode rekening tidak mengirim sumber_dana.',
        );
    }

    public function test_tidak_ada_confirm_bawaan_browser(): void
    {
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path(self::BASIS), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($berkas as $file) {
            if ($file->getExtension() !== 'tsx') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/(?<![.\w])confirm\(/',
                (string) file_get_contents($file->getPathname()),
                $file->getFilename().' memakai confirm() bawaan browser; gunakan ConfirmDialog.',
            );
        }
    }

    /**
     * Alur pengajuan satu tombol: isian disimpan di perangkat, bukan di server.
     * Tanpa autosave, operator kehilangan isian saat pindah halaman atau reload.
     */
    public function test_pengajuan_menyimpan_isian_di_perangkat_dan_tanpa_tombol_draft(): void
    {
        $isi = $this->baca('Pengajuan/Index.tsx');

        $this->assertStringContainsString('localStorage.setItem(draftKey', $isi, 'Isian pengajuan tidak diautosave.');
        $this->assertStringContainsString('localStorage.removeItem(draftKey)', $isi, 'Draf perangkat tidak dibersihkan setelah pengajuan terkirim.');
        $this->assertStringContainsString('Ajukan Pengajuan', $isi);
        $this->assertStringNotContainsString('Simpan sebagai Draft', $isi, 'Tombol draft masih tampil dan membingungkan operator.');
    }

    /** Halaman tabel riwayat yang wajib punya padanan daftar untuk layar kecil. */
    public static function halamanDaftarMobile(): array
    {
        return [
            'pengajuan' => ['Pengajuan/Index.tsx'],
            'review pengajuan' => ['Review/Index.tsx'],
            'penerimaan' => ['Penerimaan/Index.tsx'],
            'lunas bayar' => ['Penerimaan/LunasBayar.tsx'],
            'barang' => ['Master/Barang/Index.tsx'],
            'kode rekening' => ['Master/KodeRekening/Index.tsx'],
            'penyedia' => ['Master/Penyedia/Index.tsx'],
            'saldo awal' => ['SaldoAwal/Index.tsx'],
            'dokumen' => ['Dokumen/Show.tsx'],
            'daftar dokumen' => ['Laporan/DokumenList.tsx'],
            'kartu persediaan' => ['Laporan/KartuPersediaan.tsx'],
        ];
    }

    /**
     * Tabel 5–7 kolom yang hanya di-scroll horizontal menyulitkan operator di
     * ponsel: kolom aksi ada di ujung kanan. Setiap tabel riwayat harus punya
     * daftar kartu `md:hidden` dan versi tabelnya disembunyikan di bawah `md`.
     */
    #[DataProvider('halamanDaftarMobile')]
    public function test_tabel_riwayat_punya_daftar_mobile(string $berkas): void
    {
        $isi = $this->baca($berkas);

        $this->assertStringContainsString('md:hidden', $isi, "{$berkas} tidak punya daftar untuk layar kecil.");
        $this->assertStringContainsString('hidden md:block', $isi, "{$berkas} masih menampilkan tabel penuh di layar kecil.");
    }

    /**
     * Laporan panjang memang tetap scroll horizontal (banyak kolom), tetapi
     * harus punya baris ringkasan untuk ponsel.
     */
    public static function halamanLaporanMobile(): array
    {
        return [
            'laporan' => ['Laporan/Index.tsx'],
            'buku penerimaan' => ['Laporan/BukuPenerimaan.tsx'],
            'buku pengeluaran' => ['Laporan/BukuPengeluaran.tsx'],
        ];
    }

    #[DataProvider('halamanLaporanMobile')]
    public function test_halaman_laporan_punya_daftar_mobile(string $berkas): void
    {
        $isi = $this->baca($berkas);

        $this->assertStringContainsString('md:hidden', $isi, "{$berkas} tidak punya daftar untuk layar kecil.");
    }

    private function baca(string $berkas): string
    {
        $path = base_path(self::BASIS.$berkas);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
