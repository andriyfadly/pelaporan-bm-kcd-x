<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdminParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function admin(): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_parity',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $user->assignRole('admin_kcd');

        return $user;
    }

    public function test_bulan_tanpa_acuan_tidak_fallback_ke_semua_sekolah(): void
    {
        $admin = $this->admin();
        activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN Tanpa Acuan',
            'kota_kab' => 'Kota Bandung',
        ]));

        // Bulan 9 tidak punya acuan sama sekali -> target harus 0, bukan 1.
        $this->actingAs($admin)
            ->get(route('dashboard', ['bulan' => 9, 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalTarget', 0)
                ->where('totalSelesai', 0)
                ->where('totalBelum', 0)
                ->has('listSelesai', 0)
                ->has('listBelum', 0)
            );
    }

    public function test_realisasi_dari_bulan_lain_tidak_dihiung_selesai(): void
    {
        $admin = $this->admin();
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN Parity',
            'kota_kab' => 'Kota Bandung',
        ]));

        activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 9,
            'kodering' => '5.2.02.05',
            'uraian' => 'Komputer',
            'nominal' => 1_000_000,
        ]));

        // Realisasi bulan 8 -> tidak boleh dihitung selesai untuk bulan 9.
        activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.05',
            'bulan_realisasi' => 8,
            'no_spk' => 'SPK/AGUSTUS',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));

        $this->actingAs($admin)
            ->get(route('dashboard', ['bulan' => 9, 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalTarget', 1)
                ->where('totalSelesai', 0)
                ->where('totalBelum', 1)
            );
    }
}
