<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BhpDomainBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SumberDana::setAktif(null);
    }

    public function test_bhp_tables_exist_and_are_separate_from_pelaporan_bm(): void
    {
        // Tabel domain BHP dipisah per sumber dana (BOS/BOPD); penyedia bersama.
        foreach ([
            'kode_rekening',
            'barang',
            'harga_barang',
            'penerimaan',
            'penerimaan_detail',
            'pengajuan',
            'pengajuan_detail',
        ] as $suffix) {
            foreach (['bos', 'bopd'] as $dana) {
                $table = "bhp_{$dana}_{$suffix}";
                $this->assertTrue(Schema::hasTable($table), "Tabel {$table} harus ada.");
            }
        }

        $this->assertTrue(Schema::hasTable('bhp_penyedia'));
        $this->assertFalse(Schema::hasColumn('bhp_bos_barang', 'stok_tersedia'), 'Stok tidak boleh disimpan sebagai kolom denormal.');
    }

    public function test_kode_rekening_unique_per_sumber_dana(): void
    {
        KodeRekening::create(['kode' => '5.1.02.01.01.0001', 'nama' => 'Bahan Bangunan', 'sumber_dana' => 'BOS']);
        KodeRekening::create(['kode' => '5.1.02.01.01.0001', 'nama' => 'Bahan Bangunan', 'sumber_dana' => 'BOPD']);

        // Tabel terpisah per sumber dana: kode yang sama sah ada di kedua dana.
        $this->assertSame(1, KodeRekening::sumberDana('BOS')->where('kode', '5.1.02.01.01.0001')->count());
        $this->assertSame(1, KodeRekening::sumberDana('BOPD')->where('kode', '5.1.02.01.01.0001')->count());
    }

    public function test_barang_terikat_sumber_dana(): void
    {
        $rekening = KodeRekening::create([
            'kode' => '5.1.02.01.01.0002',
            'nama' => 'Bahan Kimia',
            'sumber_dana' => 'BOS',
        ]);

        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.01',
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'kode_rekening_id' => $rekening->id,
            'sumber_dana' => 'BOS',
        ]);

        $this->assertSame('BOS', $barang->sumber_dana);
        $this->assertTrue($barang->kodeRekening->is($rekening));
    }

    public function test_active_user_can_open_bhp_dashboard(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 1 Contoh', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'Admin BHP',
            'username' => 'admin.bhp',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertOk();
    }

    public function test_invalid_sumber_dana_ditolak_di_middleware(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Tolak', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'User',
            'username' => 'user.invalid.dana',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get('/distribusi-bhp/saldo-awal?sumber_dana=INVALID')
            ->assertStatus(422);
    }

    public function test_sumber_dana_tidak_disediakan_default_bos(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Default Dana', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'User',
            'username' => 'user.default.dana',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertOk();
    }

    public function test_state_sumber_dana_kembali_setelah_request(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN State', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'User',
            'username' => 'user.state.dana',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.dashboard', ['sumber_dana' => 'BOPD']))
            ->assertOk();

        // State setelah request harus kembali ke null (state request sebelumnya)
        $this->assertNull(SumberDana::aktif());
    }

    public function test_available_periods_hanya_menampilkan_period_sumber_dana_aktif(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Period', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'User',
            'username' => 'user.period.dana',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        SumberDana::setAktif('BOS');
        Penerimaan::create([
            'sekolah_id' => $sekolah->id,
            'is_saldo_awal' => true,
            'periode_tahun' => 2025,
        ]);

        SumberDana::setAktif('BOPD');
        Penerimaan::create([
            'sekolah_id' => $sekolah->id,
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);

        SumberDana::setAktif(null);

        // BOS hanya punya 2025
        $this->actingAs($user)
            ->get(route('distribusi-bhp.bos.saldo-awal.index'))
            ->assertInertia(fn ($page) => $page
                ->where('availablePeriods', [2025]));

        // BOPD hanya punya 2026
        $this->actingAs($user)
            ->get(route('distribusi-bhp.bopd.saldo-awal.index'))
            ->assertInertia(fn ($page) => $page
                ->where('availablePeriods', [2026]));
    }

    public function test_route_bos_tidak_menerima_uuid_barang_bopd(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Boundary', 'kota_kab' => 'Kota Bandung']);
        $user = User::create([
            'name' => 'Operator',
            'username' => 'operator.boundary',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');
        $barang = Barang::create([
            'kode_barang' => 'BOPD-BOUNDARY',
            'nama_barang' => 'Barang BOPD',
            'satuan' => 'Unit',
            'sumber_dana' => 'BOPD',
        ]);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.bos.barang.harga.index', $barang))
            ->assertNotFound();
    }
}
