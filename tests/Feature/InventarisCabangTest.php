<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Cabang yang belum tersentuh alur utama: sinkronisasi yang melempar error,
 * dan unit bukan-aktif di luar volume.
 */
class InventarisCabangTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Cabang',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->super = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Cabang',
            'username' => 'super_cabang',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $this->super->assignRole('super_admin');

        KunciLaporan::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ]);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'volume' => 2,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 18000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);
    }

    public function test_sinkronisasi_gagal_dilaporkan_sebagai_flash_error(): void
    {
        $this->mock(SinkronisasiInventarisService::class)
            ->shouldReceive('syncPeriode')
            ->andThrow(new RuntimeException('register bentrok'));

        $this->actingAs($this->super)
            ->post(route('pencatatan-bm.sinkronisasi.store'), [
                'sekolah_id' => $this->sekolah->id,
                'bulan' => 3,
                'tahun' => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'register bentrok'));
    }

    public function test_unit_dikembalikan_di_luar_volume_bukan_surplus_maupun_review(): void
    {
        $unit = InventarisUnit::where('unit_index', 2)->firstOrFail();
        $unit->update(['status' => InventarisUnit::STATUS_DIKEMBALIKAN]);

        Realisasi::firstOrFail()->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        $hasil = app(SinkronisasiInventarisService::class)->syncRealisasi(Realisasi::firstOrFail());

        $this->assertSame(0, $hasil->dibuat);
        $this->assertSame(1, $hasil->tetap);
        $this->assertSame(0, $hasil->surplus, 'Unit yang sudah dikembalikan tidak perlu dipensiunkan lagi.');
        $this->assertSame(0, $hasil->perluReview);
        $this->assertSame(
            InventarisUnit::STATUS_DIKEMBALIKAN,
            $unit->fresh()->status,
        );
    }
}
