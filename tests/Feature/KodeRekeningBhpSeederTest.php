<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\KodeRekening;
use App\Services\DistribusiBhp\NuspService;
use App\Support\DistribusiBhp\KodeBarang;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\KodeRekeningBhpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `kode_barang` pada kode rekening adalah prasyarat NUSP: kalau kosong,
 * NuspService melempar RuntimeException dan Tambah Barang mustahil dilakukan
 * dari UI. Seeder wajib mengisinya, bukan menyerahkannya ke assign manual.
 */
class KodeRekeningBhpSeederTest extends TestCase
{
    use RefreshDatabase;

    private const BERKAS = [
        SumberDana::BOS => 'kode-rekening-bos.xlsx',
        SumberDana::BOPD => 'kode-rekening-bopd.xlsx',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::BERKAS as $nama) {
            if (! File::exists(storage_path('app/'.$nama))) {
                $this->markTestSkipped('Berkas sumber kode rekening tidak tersedia: '.$nama);
            }
        }

        // Seeder membaca berkas nyata; sumber dana mengikuti nama tabel.
        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, fn () => KodeRekening::query()->delete());
        }

        $this->seed(KodeRekeningBhpSeeder::class);
    }

    public function test_setiap_kode_rekening_punya_kode_barang(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use ($dana): void {
                $this->assertGreaterThan(0, KodeRekening::count(), "Kode rekening {$dana} kosong.");

                $kosong = KodeRekening::where(fn ($q) => $q->whereNull('kode_barang')->orWhere('kode_barang', ''))->pluck('kode');

                $this->assertSame([], $kosong->all(), "Kode rekening {$dana} tanpa kode_barang: ".$kosong->implode(', '));
            });
        }
    }

    public function test_kode_barang_selalu_salah_satu_opsi_sah(): void
    {
        $opsi = KodeBarang::kode();

        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use ($dana, $opsi): void {
                foreach (KodeRekening::all() as $rekening) {
                    $this->assertContains($rekening->kode_barang, $opsi, "Kode {$rekening->kode} ({$dana}) memakai kode barang asing.");
                    $this->assertSame(
                        KodeBarang::uraian($rekening->kode_barang),
                        $rekening->uraian_kode_barang,
                        "Uraian kode {$rekening->kode} tidak cocok dengan kode barangnya.",
                    );
                }
            });
        }
    }

    /** Tambah Barang langsung bisa dilakukan setelah seed — tanpa assign manual. */
    public function test_nusp_dapat_dibuat_untuk_setiap_kode_rekening(): void
    {
        $nusp = app(NuspService::class);

        foreach (SumberDana::SEMUA as $dana) {
            SumberDana::dengan($dana, function () use ($dana, $nusp): void {
                foreach (KodeRekening::limit(5)->get() as $rekening) {
                    $hasil = $nusp->generate($rekening);

                    $this->assertStringStartsWith(
                        $rekening->kode_barang.'.',
                        $hasil,
                        "NUSP {$dana} untuk {$rekening->kode} tidak memakai kode barangnya.",
                    );
                }
            });
        }
    }

    public function test_menebak_kode_barang_dari_nama_rekening(): void
    {
        $this->assertSame('1.1.7.01.02', KodeBarang::untukNamaRekening('Suku Cadang-Suku Cadang Alat Bengkel')['kode']);
        $this->assertSame('1.1.7.01.02', KodeBarang::untukNamaRekening('Komponen-Komponen Peralatan')['kode']);
        $this->assertSame('1.1.7.01.04', KodeBarang::untukNamaRekening('Obat-Obatan')['kode']);
        $this->assertSame('1.1.7.01.07', KodeBarang::untukNamaRekening('Natura dan Pakan-Natura')['kode']);
        $this->assertSame('1.1.7.01.03', KodeBarang::untukNamaRekening('Alat/Bahan untuk Kegiatan Kantor-Kertas dan Cover')['kode']);
        // Baris sumber menulis "Kanto" tanpa 'r'.
        $this->assertSame('1.1.7.01.03', KodeBarang::untukNamaRekening('Alat/Bahan untuk Kegiatan Kanto Alat Listrik')['kode']);
        $this->assertSame('1.1.7.01.01', KodeBarang::untukNamaRekening('Bahan-Bahan Kimia')['kode']);
        $this->assertSame('1.1.7.01.01', KodeBarang::untukNamaRekening('Makanan dan Minuman Rapat')['kode']);
    }

    /** Seeder idempotent: dijalankan dua kali tidak menggandakan baris. */
    public function test_seeder_idempotent(): void
    {
        $sebelum = [];
        foreach (SumberDana::SEMUA as $dana) {
            $sebelum[$dana] = SumberDana::dengan($dana, fn (): int => KodeRekening::count());
        }

        $this->seed(KodeRekeningBhpSeeder::class);

        foreach (SumberDana::SEMUA as $dana) {
            $this->assertSame(
                $sebelum[$dana],
                SumberDana::dengan($dana, fn (): int => KodeRekening::count()),
                "Kode rekening {$dana} bertambah saat seeder diulang.",
            );
        }
    }
}
