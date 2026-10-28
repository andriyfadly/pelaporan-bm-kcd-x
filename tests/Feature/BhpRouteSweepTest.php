<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\DemoDistribusiBhpSmkn4KuninganSeeder;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sapu seluruh halaman GET Distribusi BHP untuk setiap peran, dalam ketiga
 * bentuk route: `/bos/`, `/bopd/`, dan legacy tanpa prefix.
 *
 * Tujuannya menangkap halaman yang 500, halaman yang memakai sumber dana salah,
 * dan tautan yang mengarah ke 403 — bukan menguji isi tiap laporan (itu sudah
 * punya test sendiri).
 */
class BhpRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private User $reviewer;

    private User $operatorDivisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create([
            'nama_sekolah' => 'SMK NEGERI 4 KUNINGAN',
            'npsn' => DemoDistribusiBhpSmkn4KuninganSeeder::NPSN,
            'kota_kab' => 'KUNINGAN',
        ]);

        $this->seed(DemoDistribusiBhpSmkn4KuninganSeeder::class);

        $this->operator = User::where('username', 'demo-operator-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN)->firstOrFail();
        $this->reviewer = User::where('username', 'demo-reviewer-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN)->firstOrFail();
        $this->operatorDivisi = User::where('username', 'like', 'demo-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN.'-%')->firstOrFail();
    }

    /**
     * Halaman yang harus terbuka untuk pemilik permission `lihat-distribusi-bhp`
     * pada tiap bentuk prefix. Parameter periode ikut supaya filter tidak
     * menabrak validasi.
     *
     * @return array<string, array{string}>
     */
    public static function halaman(): array
    {
        $halaman = [
            'laporan' => 'laporan?tahun=2026&bulan=6',
            'bast internal' => 'laporan/bast-internal',
            'bap' => 'laporan/berita-acara-pemeriksaan',
            'bast serah terima' => 'laporan/berita-acara-serah-terima',
            'buku pengeluaran' => 'laporan/buku-pengeluaran?tahun=2026&bulan=6',
            'buku penerimaan' => 'laporan/buku-penerimaan?tahun=2026&bulan=6',
            'buku penyaluran' => 'laporan/buku-penyaluran',
            'kartu persediaan' => 'laporan/kartu-persediaan?tahun=2026&bulan=6',
            'stok opname' => 'laporan/stok-opname',
            'surat permintaan' => 'laporan/surat-permintaan-barang',
            'surat penyaluran' => 'laporan/surat-perintah-penyaluran',
            'master barang' => 'master/barang',
            'kode rekening' => 'master/kode-rekening',
            'penerimaan' => 'penerimaan',
            'lunas bayar' => 'penerimaan/lunas-bayar',
            'pengajuan' => 'pengajuan',
        ];

        $data = [];

        foreach ($halaman as $label => $path) {
            $data[$label] = [$path];
        }

        return $data;
    }

    #[DataProvider('halaman')]
    public function test_halaman_bos_terbuka_untuk_operator(string $path): void
    {
        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/bos/'.$path)
            ->assertOk();
    }

    #[DataProvider('halaman')]
    public function test_halaman_bopd_terbuka_untuk_operator(string $path): void
    {
        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/bopd/'.$path)
            ->assertOk();
    }

    #[DataProvider('halaman')]
    public function test_halaman_legacy_tanpa_prefix_tetap_terbuka(string $path): void
    {
        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/'.$path)
            ->assertOk();
    }

    public function test_halaman_dokumen_pengajuan_terbuka_dari_daftar_pengajuan(): void
    {
        $pengajuan = SumberDana::dengan('BOPD', fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->where('status', 'approved')
            ->firstOrFail());

        // Prefix wajib: route legacy akan memakai sumber dana aktif request.
        $this->actingAs($this->operator)
            ->get("/distribusi-bhp/bopd/pengajuan/{$pengajuan->id}/dokumen")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('DistribusiBhp/Dokumen/Show'));

        // Dokumen BOPD tidak boleh terbuka lewat prefix BOS.
        $this->actingAs($this->operator)
            ->get("/distribusi-bhp/bos/pengajuan/{$pengajuan->id}/dokumen")
            ->assertNotFound();
    }

    public function test_pdf_dokumen_bopd_tidak_bisa_dicetak_lewat_prefix_bos(): void
    {
        $pengajuan = SumberDana::dengan('BOPD', fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->where('status', 'approved')
            ->firstOrFail());

        $dokumen = SumberDana::dengan('BOPD', fn (): Dokumen => Dokumen::where('bhp_pengajuan_id', $pengajuan->id)
            ->where('jenis', 'nota_permintaan')
            ->firstOrFail());

        $this->actingAs($this->operator)
            ->get("/distribusi-bhp/bos/pengajuan/{$pengajuan->id}/dokumen/{$dokumen->id}/pdf")
            ->assertNotFound();
    }

    public function test_review_pengajuan_dan_settings_hanya_untuk_pemegang_permission(): void
    {
        // Operator sekolah: punya cetak/kelola tapi tidak me-review dan tidak
        // mengelola master, jadi tetap 403 — bukan 500.
        foreach (['bos', 'bopd'] as $prefix) {
            $this->actingAs($this->operator)
                ->get("/distribusi-bhp/{$prefix}/review-pengajuan")
                ->assertForbidden();
        }

        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/settings')
            ->assertForbidden();

        // Reviewer memegang review + kelola master.
        foreach (['bos', 'bopd'] as $prefix) {
            $this->actingAs($this->reviewer)
                ->get("/distribusi-bhp/{$prefix}/review-pengajuan")
                ->assertOk();
        }

        $this->actingAs($this->reviewer)
            ->get('/distribusi-bhp/settings?sumber_dana=BOPD')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sumberDana', 'BOPD'));
    }

    public function test_operator_divisi_hanya_melihat_pengajuan_divisinya(): void
    {
        $response = $this->actingAs($this->operatorDivisi)
            ->get('/distribusi-bhp/bos/pengajuan')
            ->assertOk();

        $items = $response->viewData('page')['props']['items']['data'];

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame(
                $this->operatorDivisi->divisi_id,
                SumberDana::dengan('BOS', fn (): ?string => Pengajuan::whereKey($item['id'])->value('divisi_id')),
                'Operator divisi tidak boleh melihat pengajuan divisi lain.',
            );
        }
    }

    public function test_semua_barang_yang_tampil_di_pengajuan_milik_sumber_dana_halaman(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $response = $this->actingAs($this->operator)
                ->get('/distribusi-bhp/'.strtolower($dana).'/pengajuan')
                ->assertOk();

            $barang = $response->viewData('page')['props']['barang'];
            $this->assertNotEmpty($barang, "Halaman pengajuan {$dana} tidak memuat barang.");

            $idDana = SumberDana::dengan($dana, fn (): array => Barang::pluck('id')->all());

            foreach ($barang as $item) {
                $this->assertContains(
                    $item['id'],
                    $idDana,
                    "Barang {$item['nama_barang']} di halaman {$dana} bukan milik tabel {$dana}.",
                );
            }
        }
    }

    public function test_lunas_bayar_hanya_memuat_penerimaan_belum_dibayar(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $response = $this->actingAs($this->operator)
                ->get('/distribusi-bhp/'.strtolower($dana).'/penerimaan/lunas-bayar')
                ->assertOk();

            $items = $response->viewData('page')['props']['items']['data'];

            $this->assertNotEmpty($items, "Lunas bayar {$dana} tidak boleh kosong pada dataset demo.");

            foreach ($items as $item) {
                $this->assertEmpty($item['no_kwitansi'], 'Baris yang sudah berkwitansi tidak boleh muncul di Lunas Bayar.');
            }

            // Saldo awal bukan pembelian, jadi tidak boleh muncul di sini.
            $idSaldoAwal = SumberDana::dengan($dana, fn (): array => Penerimaan::where('is_saldo_awal', true)->pluck('id')->all());

            foreach ($items as $item) {
                $this->assertNotContains($item['id'], $idSaldoAwal, 'Saldo awal tidak boleh muncul di Lunas Bayar.');
            }
        }
    }
}
