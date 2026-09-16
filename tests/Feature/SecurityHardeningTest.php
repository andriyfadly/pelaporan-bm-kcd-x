<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN 1 Hardening'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function operator(Sekolah $sekolah, string $username = 'operator_hard'): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function adminKcd(string $username = 'admin_hard'): User
    {
        $user = User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('Password123!'),
        ]);
        $user->assignRole('admin_kcd');

        return $user;
    }

    public function test_registrasi_publik_dimatikan(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Penyusup',
            'username' => 'penyusup',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['username' => 'penyusup']);
    }

    public function test_user_tanpa_role_tidak_bisa_akses_admin_user(): void
    {
        $user = User::create([
            'name' => 'Tanpa Role',
            'username' => 'tanpa_role',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user)->get(route('admin.user.index'))->assertForbidden();
    }

    public function test_operator_tidak_bisa_akses_admin(): void
    {
        $operator = $this->operator($this->sekolah());

        $this->actingAs($operator)->get(route('admin.user.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.kode-barang.index'))->assertForbidden();
    }

    public function test_operator_tidak_bisa_toggle_kunci_sekolah_lain(): void
    {
        $sekolahLain = $this->sekolah('SMKN 2 Lain');
        $operator = $this->operator($this->sekolah());

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.kunci-laporan.toggle'), [
                'sekolah_id' => $sekolahLain->id,
                'bulan' => 5,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pelaporan_bm_kunci_laporan', 0);
    }

    public function test_admin_bisa_toggle_kunci(): void
    {
        $sekolah = $this->sekolah();
        $admin = $this->adminKcd();

        $this->actingAs($admin)
            ->post(route('pelaporan-bm.kunci-laporan.toggle'), [
                'sekolah_id' => $sekolah->id,
                'bulan' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_kunci_laporan', [
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
        ]);
    }

    public function test_operator_input_sekolah_id_diabaikan_dan_dipaksa_ke_sekolahnya(): void
    {
        $sekolahOperator = $this->sekolah('SMKN Operator');
        $sekolahLain = $this->sekolah('SMKN Korban');
        $operator = $this->operator($sekolahOperator);

        Spj::create([
            'sekolah_id' => $sekolahLain->id,
            'no_spk' => 'SPK-KORBAN-01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Barang Sekolah Lain',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 5000000,
            'nilai_perolehan' => 5000000,
        ]);

        // Coba intip data sekolah lain dengan parameter sekolah_id
        $this->actingAs($operator)
            ->get(route('pelaporan-bm.spj.index', ['bulan' => 5, 'sekolah_id' => $sekolahLain->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('items', []));
    }

    public function test_operator_tidak_bisa_hapus_acuan_sekolah_lain(): void
    {
        $sekolahLain = $this->sekolah('SMKN Korban Acuan');
        $operator = $this->operator($this->sekolah());

        $acuan = Acuan::create([
            'sekolah_id' => $sekolahLain->id,
            'kodering' => '5.2.02.05',
            'uraian' => 'Acuan Sekolah Lain',
            'nominal' => 1000000,
            'bulan' => 5,
        ]);

        $this->actingAs($operator)
            ->delete(route('pelaporan-bm.acuan.destroy', $acuan))
            ->assertForbidden();

        $this->assertDatabaseHas('pelaporan_bm_acuan', ['id' => $acuan->id]);
    }

    public function test_operator_destroy_all_hanya_menghapus_acuan_sekolahnya(): void
    {
        $sekolahOperator = $this->sekolah('SMKN Operator DA');
        $sekolahLain = $this->sekolah('SMKN Korban DA');
        $operator = $this->operator($sekolahOperator);

        Acuan::create([
            'sekolah_id' => $sekolahOperator->id,
            'kodering' => '5.2.02.05',
            'uraian' => 'Milik sendiri',
            'nominal' => 1000000,
            'bulan' => 5,
        ]);

        $acuanLain = Acuan::create([
            'sekolah_id' => $sekolahLain->id,
            'kodering' => '5.2.02.05',
            'uraian' => 'Milik orang lain',
            'nominal' => 2000000,
            'bulan' => 5,
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.destroy-all'), ['bulan' => 5])
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_acuan', ['sekolah_id' => $sekolahOperator->id]);
        $this->assertDatabaseHas('pelaporan_bm_acuan', ['id' => $acuanLain->id]);
    }

    public function test_user_nonaktif_tidak_bisa_login(): void
    {
        $user = User::create([
            'name' => 'Non Aktif',
            'username' => 'nonaktif',
            'password' => bcrypt('Password123!'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => 'nonaktif',
            'password' => 'Password123!',
        ]);

        $this->assertGuest();
    }

    public function test_acuan_id_lintas_sekolah_ditolak_saat_store_spj(): void
    {
        $sekolahOperator = $this->sekolah('SMKN A Acuan');
        $sekolahLain = $this->sekolah('SMKN B Acuan');
        $operator = $this->operator($sekolahOperator);

        $acuanLain = Acuan::create([
            'sekolah_id' => $sekolahLain->id,
            'kodering' => '5.2.02.05',
            'uraian' => 'Acuan orang lain',
            'nominal' => 1000000,
            'bulan' => 5,
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.store'), [
                'no_spk' => 'SPK-X',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.05',
                'nama_barang' => 'Barang Uji',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 1000000,
                'acuan_id' => $acuanLain->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('pelaporan_bm_spj', ['no_spk' => 'SPK-X']);
    }

    public function test_rekapan_scope_sekolah_untuk_operator(): void
    {
        $sekolahOperator = $this->sekolah('SMKN Sendiri');
        $sekolahLain = $this->sekolah('SMKN Rahasia');
        $operator = $this->operator($sekolahOperator);

        $this->actingAs($operator)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('items.0.nama_sekolah', 'SMKN Sendiri'));
    }

    public function test_import_acuan_menolak_berkas_bukan_xlsx(): void
    {
        $operator = $this->operator($this->sekolah());

        $file = UploadedFile::fake()->create('jahat.php', 10, 'application/x-php');

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    private function operatorTanpaSekolah(string $username = 'op_tanpa'): User
    {
        $user = User::create([
            'name' => 'Operator Tanpa Sekolah',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function endpointLintasSekolahProvider(): array
    {
        return [
            'cetak' => ['pelaporan-bm.cetak'],
            'realisasi index' => ['pelaporan-bm.realisasi.index'],
            'rekapan' => ['pelaporan-bm.rekapan.index'],
            'spj index' => ['pelaporan-bm.spj.index'],
            'cetak unduh' => ['pelaporan-bm.cetak.unduh'],
            'realisasi unduh' => ['pelaporan-bm.realisasi.unduh'],
            'spj unduh' => ['pelaporan-bm.unduh'],
        ];
    }

    #[DataProvider('endpointLintasSekolahProvider')]
    public function test_operator_tanpa_sekolah_ditolak_di_endpoint_lintas_sekolah(string $routeName): void
    {
        // Akun operator_sekolah tanpa sekolah_id tidak boleh melihat data seluruh sekolah.
        $this->actingAs($this->operatorTanpaSekolah("op_tanpa_{$routeName}"))
            ->get(route($routeName))
            ->assertForbidden();
    }
}
