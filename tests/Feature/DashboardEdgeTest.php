<?php

namespace Tests\Feature;

use App\Http\Controllers\PelaporanBm\DashboardController;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function admin(string $username = 'admin_dash_edge'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $user->assignRole('admin_kcd');

        return $user;
    }

    private function sekolah(string $nama, ?string $username = null): Sekolah|array
    {
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));

        if ($username === null) {
            return $sekolah;
        }

        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        return [$sekolah, $user];
    }

    private function acuan(Sekolah $sekolah, int $bulan): void
    {
        activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => $bulan,
            'kodering' => '5.2.02.05',
            'uraian' => 'Komputer',
            'nominal' => 1_000_000,
        ]));
    }

    public function test_admin_melihat_selesai_dan_belum_berdasarkan_data_bulan(): void
    {
        $admin = $this->admin();
        $selesai = $this->sekolah('SMKN Selesai');
        $belum = $this->sekolah('SMKN Belum');

        $this->acuan($selesai, 6);
        $this->acuan($belum, 6);

        // Hanya "selesai" yang punya Realisasi di bulan 6.
        activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $selesai->id,
            'kodering_belanja' => '5.2.02.05',
            'bulan_realisasi' => 6,
            'no_spk' => 'SPK/SELESAI',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));

        $this->actingAs($admin)
            ->get(route('dashboard', ['bulan' => 6, 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isAdmin', true)
                ->where('totalTarget', 2)
                ->where('totalSelesai', 1)
                ->where('totalBelum', 1)
                ->has('listSelesai', 1)
                ->has('listBelum', 1)
                ->where('listSelesai.0.nama', 'SMKN Selesai')
                ->where('listBelum.0.nama', 'SMKN Belum')
            );
    }

    public function test_admin_tanpa_data_acuan_tidak_menampilkan_target(): void
    {
        $admin = $this->admin('admin_dash_all');
        $this->sekolah('SMKN Satu');
        $this->sekolah('SMKN Dua');

        // Tidak ada acuan di bulan 6 -> target 0 (paritas legacy index_admin.php).
        $this->actingAs($admin)
            ->get(route('dashboard', ['bulan' => 6, 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalTarget', 0)
                ->where('totalBelum', 0)
                ->has('listBelum', 0)
            );
    }

    public function test_dashboard_sekolah_menandai_menunggu_approval_sebagai_selesai(): void
    {
        [$sekolah, $operator] = $this->sekolah('SMKN Status', 'op_dash_status');

        // Bulan lapor = bulan sekarang - 1; isi kunci untuk bulan itu dengan
        // status menunggu_approval -> dashboard harus menandai SELESAI.
        $bulanLapor = (int) date('n') === 1 ? 12 : (int) date('n') - 1;

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => $bulanLapor,
            'status_kirim' => 'menunggu_approval',
            'status_kunci' => true,
        ]));

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isAdmin', false)
                ->where('bulanLapor', $bulanLapor)
                ->where('statusBulanLapor', 'SELESAI')
            );
    }

    public function test_dashboard_sekolah_tanpa_kunci_belum_selesai(): void
    {
        [, $operator] = $this->sekolah('SMKN Kosong', 'op_dash_kosong');

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isAdmin', false)
                ->where('statusBulanLapor', 'BELUM SELESAI')
                ->where('totalAcuan', 0)
                ->where('totalRealisasi', 0)
            );
    }

    public function test_bulan_lapor_januari_mundur_ke_desember(): void
    {
        $controller = new DashboardController;
        $method = new \ReflectionMethod($controller, 'bulanLapor');
        $method->setAccessible(true);

        $this->assertSame(12, $method->invoke($controller, 1));
        $this->assertSame(1, $method->invoke($controller, 2));
        $this->assertSame(11, $method->invoke($controller, 12));
        $this->assertContains($method->invoke($controller, null), range(1, 12));
    }
}
