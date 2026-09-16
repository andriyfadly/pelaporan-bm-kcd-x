<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapanDanKunciTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    public function test_admin_can_view_rekapan_and_toggle_lock_and_update_status(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_rekap',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');

        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Bandung',
            'kota_kab' => 'Kota Bandung',
        ]);

        Acuan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'kodering' => '5.2.02.05',
            'uraian' => 'Komputer',
            'nominal' => 20000000,
        ]);

        Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK-01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Core i7',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 20000000,
            'nilai_perolehan' => 20000000,
        ]);

        // Rekapan index
        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5, 'search' => 'Bandung']))
            ->assertOk();

        // Kunci toggle
        $this->actingAs($admin)
            ->post(route('pelaporan-bm.kunci-laporan.toggle'), [
                'sekolah_id' => $sekolah->id,
                'bulan' => 5,
            ])
            ->assertRedirect();

        $kunci = KunciLaporan::where('sekolah_id', $sekolah->id)->where('bulan', '5')->first();
        $this->assertNotNull($kunci);
        $this->assertTrue((bool) $kunci->status_kunci);

        // Kunci update status: disetujui
        $this->actingAs($admin)
            ->post(route('pelaporan-bm.kunci-laporan.status'), [
                'sekolah_id' => $sekolah->id,
                'bulan' => 5,
                'status_kirim' => 'disetujui',
            ])
            ->assertRedirect();

        $kunci->refresh();
        $this->assertEquals('disetujui', $kunci->status_kirim);
        $this->assertTrue((bool) $kunci->status_kunci);

        // Kunci update status: draft
        $this->actingAs($admin)
            ->post(route('pelaporan-bm.kunci-laporan.status'), [
                'sekolah_id' => $sekolah->id,
                'bulan' => 5,
                'status_kirim' => 'draft',
            ])
            ->assertRedirect();

        $kunci->refresh();
        $this->assertEquals('draft', $kunci->status_kirim);
        $this->assertFalse((bool) $kunci->status_kunci);
    }
}
