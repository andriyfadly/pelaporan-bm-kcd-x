<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman di prefix `/distribusi-bhp/{bos|bopd}/...` wajib memakai sumber dana
 * dari URL, bukan default BOS. Tanpa ini halaman BOPD menampilkan data BOS dan
 * tombol export/cetak ikut membawa dana yang salah.
 */
class BhpSumberDanaPrefixTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN PREFIX',
            'kota_kab' => 'KOTA BANDUNG',
            'npsn' => '33334444',
        ]);

        $this->operator = User::create([
            'name' => 'Operator Prefix',
            'username' => 'op.prefix',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');

        // Satu barang + penerimaan per sumber dana, nama sengaja berbeda.
        foreach (['BOS', 'BOPD'] as $dana) {
            $barang = Barang::create([
                'kode_barang' => 'P-'.$dana,
                'nama_barang' => 'Barang '.$dana,
                'satuan' => 'Pcs',
                'sumber_dana' => $dana,
            ]);

            $penerimaan = Penerimaan::create([
                'sekolah_id' => $this->sekolah->id,
                'no_bast' => 'BAST/'.$dana,
                'tanggal_bast' => '2026-06-10',
                'is_saldo_awal' => true,
                'periode_tahun' => 2026,
                'sumber_dana' => $dana,
            ]);

            $penerimaan->details()->create([
                'barang_id' => $barang->id,
                'jumlah' => 10,
                'harga_satuan' => 1000,
                'nilai_total' => 10000,
            ]);
        }
    }

    /**
     * @return array<string, array{string,string}>
     */
    public static function halaman(): array
    {
        return [
            'laporan' => ['laporan', 'sumberDana'],
            'buku pengeluaran' => ['laporan/buku-pengeluaran', 'sumberDana'],
            'buku penerimaan' => ['laporan/buku-penerimaan', 'sumberDana'],
            'kartu persediaan' => ['laporan/kartu-persediaan', 'sumberDana'],
            'master barang' => ['master/barang', 'sumberDana'],
            'master kode rekening' => ['master/kode-rekening', 'sumberDana'],
            'pengajuan' => ['pengajuan', 'sumberDana'],
            'penerimaan' => ['penerimaan', 'sumberDana'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_prefix_bopd_memakai_bopd(string $path, string $prop): void
    {
        $this->actingAs($this->operator)
            ->get("/distribusi-bhp/bopd/{$path}?tahun=2026&bulan=6")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where($prop, 'BOPD'));
    }

    #[DataProvider('halaman')]
    public function test_prefix_bos_memakai_bos(string $path, string $prop): void
    {
        $this->actingAs($this->operator)
            ->get("/distribusi-bhp/bos/{$path}?tahun=2026&bulan=6")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where($prop, 'BOS'));
    }

    public function test_export_tanpa_parameter_sumber_dana_mengikuti_prefix(): void
    {
        $response = $this->actingAs($this->operator)
            ->get('/distribusi-bhp/bopd/laporan/export?jenis=stok&tahun=2026&bulan=6');

        $response->assertOk();
        $this->assertStringContainsString(
            'laporan_stok_bhp_BOPD_2026-06.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_laporan_bopd_tidak_memuat_barang_bos(): void
    {
        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/bopd/laporan?tahun=2026&bulan=6')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('stok', 1)
                ->where('stok.0.nama_barang', 'Barang BOPD'));
    }

    /**
     * Template unduhan juga harus mengikuti prefix: `/bopd/` tanpa
     * `sumber_dana` dulu diam-diam mengunduh template BOS.
     */
    public function test_template_mengikuti_prefix_sumber_dana(): void
    {
        foreach (['bos', 'bopd'] as $dana) {
            $harapan = strtoupper($dana);

            foreach (['master/barang/template', 'saldo-awal/template'] as $path) {
                $response = $this->actingAs($this->operator)
                    ->get("/distribusi-bhp/{$dana}/{$path}");

                $response->assertOk();
                $this->assertStringContainsString(
                    "-{$harapan}.xlsx",
                    (string) $response->headers->get('content-disposition'),
                    "Template {$path} di prefix {$dana} harus memakai sumber dana {$harapan}.",
                );
            }
        }
    }

    public function test_template_menolak_sumber_dana_asing(): void
    {
        $this->actingAs($this->operator)
            ->get('/distribusi-bhp/bos/master/barang/template?sumber_dana=BOHONG')
            ->assertStatus(422);
    }
}
