<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventarisPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function user(?Sekolah $sekolah, string $role, string $username): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah?->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole($role);

        return $user;
    }

    private function unit(Sekolah $sekolah, string $register = '001'): InventarisUnit
    {
        KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ]);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);

        return InventarisUnit::where('sekolah_id', $sekolah->id)->where('nomor_register', $register)->firstOrFail();
    }

    public function test_permission_pencatatan_bm_tersedia_dan_terpasang_ke_role(): void
    {
        foreach ([
            'lihat-pencatatan-bm', 'kelola-inventaris-bm', 'kelola-ruang-bm', 'kelola-pegawai-bm',
            'mutasi-inventaris-bm', 'proses-pengembalian-bm', 'cetak-inventaris-bm', 'sinkronisasi-inventaris-bm',
        ] as $nama) {
            $this->assertTrue(Permission::where('name', $nama)->exists(), "Permission {$nama} belum di-seed.");
        }

        $operator = $this->user(null, 'operator_sekolah', 'cek_operator');
        foreach ([
            'lihat-pencatatan-bm', 'kelola-inventaris-bm', 'kelola-ruang-bm', 'kelola-pegawai-bm',
            'mutasi-inventaris-bm', 'proses-pengembalian-bm', 'cetak-inventaris-bm',
        ] as $nama) {
            $this->assertTrue($operator->can($nama), "Operator harus punya {$nama}.");
        }
        $this->assertFalse($operator->can('sinkronisasi-inventaris-bm'), 'Operator tidak boleh re-sync.');

        $admin = $this->user(null, 'admin_kcd', 'cek_admin');
        $this->assertTrue($admin->can('lihat-pencatatan-bm'));
        $this->assertTrue($admin->can('cetak-inventaris-bm'));

        $superAdmin = $this->user(null, 'super_admin', 'cek_super');
        $this->assertTrue($superAdmin->can('sinkronisasi-inventaris-bm'));
    }

    public function test_operator_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah('SMA A');
        $this->unit($sekolah);

        $user = $this->user($sekolah, 'operator_divisi', 'op_divisi');
        $user->givePermissionTo('lihat-distribusi-bhp');

        $this->actingAs($user)->get(route('pencatatan-bm.inventaris.index'))->assertForbidden();
    }

    public function test_operator_sekolah_dapat_membuka_halaman(): void
    {
        $sekolah = $this->sekolah('SMA A');
        $this->unit($sekolah);
        $user = $this->user($sekolah, 'operator_sekolah', 'op_ok');

        $this->actingAs($user)->get(route('pencatatan-bm.inventaris.index'))->assertOk();
        $this->actingAs($user)->get(route('pencatatan-bm.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('master-data.ruang.index'))->assertOk();
        $this->actingAs($user)->get(route('master-data.pegawai.index'))->assertOk();
    }

    public function test_operator_tidak_boleh_membuka_halaman_sinkronisasi(): void
    {
        $sekolah = $this->sekolah('SMA A');
        $user = $this->user($sekolah, 'operator_sekolah', 'op_nosync');

        $this->actingAs($user)->get(route('pencatatan-bm.sinkronisasi.index'))->assertForbidden();
    }

    public function test_admin_kcd_tanpa_sekolah_melihat_ringkasan_lintas_sekolah(): void
    {
        $sekolah = $this->sekolah('SMA A');
        $this->unit($sekolah);
        $admin = $this->user(null, 'admin_kcd', 'admin_dash');

        $this->actingAs($admin)
            ->get(route('pencatatan-bm.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ringkasan.totalUnit', 1)
            );
    }

    public function test_ruang_dan_pegawai_terbatas_pada_sekolah_pengguna(): void
    {
        $sekolah = $this->sekolah('SMA A');
        $user = $this->user($sekolah, 'operator_sekolah', 'op_ruang');

        activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'Ruang Guru']));
        activity()->withoutLogging(fn () => Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Budi']));

        $this->actingAs($user)
            ->get(route('master-data.ruang.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('ruang', 1)->where('ruang.0.nama_ruang', 'Ruang Guru'));

        $this->actingAs($user)
            ->get(route('master-data.pegawai.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pegawai', 1)->where('pegawai.0.nama_lengkap', 'Budi'));
    }
}
