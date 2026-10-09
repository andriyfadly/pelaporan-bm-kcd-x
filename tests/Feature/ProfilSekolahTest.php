<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilSekolahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Profil'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function user(Sekolah $sekolah, string $username, string $role = 'operator_sekolah'): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_operator_dapat_melihat_dan_mengisi_profil_sekolah_sendiri(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.profil');

        $this->actingAs($operator)
            ->get(route('master-data.profil-sekolah.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MasterData/ProfilSekolah/Edit'))
            ->assertInertia(fn ($page) => $page->where('sekolah.nama_sekolah', 'SMKN Profil'));

        $this->actingAs($operator)
            ->put(route('master-data.profil-sekolah.update'), [
                'alamat' => 'Jl. Pendidikan No. 1',
                'telepon' => '022-1234567',
                'website' => 'https://smknprofil.sch.id',
                'email' => 'info@smknprofil.sch.id',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('master_data_sekolah', [
            'id' => $sekolah->id,
            'alamat' => 'Jl. Pendidikan No. 1',
            'telepon' => '022-1234567',
            'email' => 'info@smknprofil.sch.id',
        ]);
    }

    public function test_operator_tidak_bisa_mengubah_nama_dan_npsn(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.guard.nama');

        $this->actingAs($operator)
            ->put(route('master-data.profil-sekolah.update'), [
                'nama_sekolah' => 'Sekolah Diretas',
                'npsn' => '99999999',
                'alamat' => 'Jl. Baru',
            ])
            ->assertRedirect();

        $sekolah->refresh();
        $this->assertSame('SMKN Profil', $sekolah->nama_sekolah);
        $this->assertNull($sekolah->npsn);
        $this->assertSame('Jl. Baru', $sekolah->alamat);
    }

    public function test_profil_terisolasi_antar_sekolah(): void
    {
        $sekolahA = $this->sekolah('SMKN A Profil');
        $sekolahB = $this->sekolah('SMKN B Profil');
        $operatorA = $this->user($sekolahA, 'op.profil.a');

        $this->actingAs($operatorA)
            ->put(route('master-data.profil-sekolah.update'), ['alamat' => 'Alamat A'])
            ->assertRedirect();

        $sekolahB->refresh();
        $this->assertNull($sekolahB->alamat);
        $this->assertSame('Alamat A', $sekolahA->refresh()->alamat);
    }

    public function test_user_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = $this->user($sekolah, 'bend.profil', 'bendahara_sekolah');

        $this->actingAs($bendahara)
            ->get(route('master-data.profil-sekolah.edit'))
            ->assertForbidden();

        $this->actingAs($bendahara)
            ->put(route('master-data.profil-sekolah.update'), ['alamat' => 'X'])
            ->assertForbidden();
    }

    public function test_email_tidak_valid_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.email');

        $this->actingAs($operator)
            ->from(route('master-data.profil-sekolah.edit'))
            ->put(route('master-data.profil-sekolah.update'), ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    }

    private function adminKcd(string $username = 'admin.kcd.profil'): User
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => null,
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('admin_kcd');

        return $admin;
    }

    public function test_admin_tanpa_sekolah_diminta_memilih_dulu(): void
    {
        $this->sekolah('SMKN Pilihan A');
        $this->sekolah('SMKN Pilihan B');

        $this->actingAs($this->adminKcd())
            ->get(route('master-data.profil-sekolah.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/ProfilSekolah/Edit')
                ->where('sekolah', null)
                ->has('sekolahOptions', 2)
            );
    }

    public function test_admin_tanpa_sekolah_tidak_bisa_menyimpan(): void
    {
        $sekolah = $this->sekolah('SMKN Belum Dipilih');

        $this->actingAs($this->adminKcd('admin.kcd.2'))
            ->put(route('master-data.profil-sekolah.update'), ['alamat' => 'Alamat Entah'])
            ->assertSessionHasErrors('sekolah_id');

        $sekolah->refresh();
        $this->assertNull($sekolah->alamat);
    }

    public function test_admin_dengan_sekolah_terpilih_dapat_mengubah_profil(): void
    {
        $sekolah = $this->sekolah('SMKN Dipilih');
        $admin = $this->adminKcd('admin.kcd.3');

        $this->actingAs($admin)
            ->get(route('master-data.profil-sekolah.edit', ['sekolah_id' => $sekolah->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sekolah.id', $sekolah->id)
                ->where('sekolahTerpilih', $sekolah->id)
            );

        $this->actingAs($admin)
            ->put(route('master-data.profil-sekolah.update'), [
                'sekolah_id' => $sekolah->id,
                'alamat' => 'Alamat Pilihan',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Alamat Pilihan', $sekolah->refresh()->alamat);
    }

    public function test_admin_tidak_bisa_mengubah_profil_sekolah_di_luar_pilihannya(): void
    {
        $sekolahA = $this->sekolah('SMKN A Pilih');
        $sekolahB = $this->sekolah('SMKN B Pilih');

        $this->actingAs($this->adminKcd('admin.kcd.4'))
            ->put(route('master-data.profil-sekolah.update'), [
                'sekolah_id' => $sekolahA->id,
                'alamat' => 'Alamat A',
            ])
            ->assertRedirect();

        $this->assertSame('Alamat A', $sekolahA->refresh()->alamat);
        $this->assertNull($sekolahB->refresh()->alamat);
    }
}
