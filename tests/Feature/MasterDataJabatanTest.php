<?php

namespace Tests\Feature;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataJabatanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMA Negeri 1'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op.jabatan'): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_operator_dapat_melihat_dan_menambah_jabatan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->get(route('master-data.jabatan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MasterData/Jabatan/Index'));

        $this->actingAs($operator)
            ->post(route('master-data.jabatan.store'), [
                'kode' => 'wakasek_kurikulum',
                'nama' => 'Wakasek Kurikulum',
                'kategori' => 'wakil_pimpinan',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('master_data_jabatan', [
            'sekolah_id' => $sekolah->id,
            'kode' => 'wakasek_kurikulum',
        ]);
    }

    public function test_kode_jabatan_bentrok_ditolak_di_sekolah_yang_sama(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        Jabatan::create(['sekolah_id' => $sekolah->id, 'kode' => 'bendahara', 'nama' => 'Bendahara']);

        $this->actingAs($operator)
            ->post(route('master-data.jabatan.store'), [
                'kode' => 'bendahara',
                'nama' => 'Bendahara Sekolah',
            ])
            ->assertSessionHasErrors('kode');
    }

    public function test_kode_jabatan_hanya_huruf_kecil_angka_dan_garis_bawah(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->post(route('master-data.jabatan.store'), [
                'kode' => 'Wakasek Kurikulum',
                'nama' => 'Wakasek Kurikulum',
            ])
            ->assertSessionHasErrors('kode');
    }

    public function test_jabatan_yang_sudah_dipakai_dinonaktifkan_bukan_dihapus(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $jabatan = Jabatan::create(['sekolah_id' => $sekolah->id, 'kode' => 'kepala_sekolah', 'nama' => 'Kepala Sekolah']);
        $pegawai = Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Budi']);
        PenugasanPegawai::create([
            'sekolah_id' => $sekolah->id,
            'pegawai_id' => $pegawai->id,
            'jabatan_id' => $jabatan->id,
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => '2026-06-30',
        ]);

        $this->actingAs($operator)
            ->delete(route('master-data.jabatan.destroy', $jabatan))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('master_data_jabatan', ['id' => $jabatan->id]);
        $this->assertFalse($jabatan->fresh()->aktif);
        $this->assertSame(1, PenugasanPegawai::count());
    }

    public function test_jabatan_tanpa_riwayat_dihapus_biasa(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $jabatan = Jabatan::create(['sekolah_id' => $sekolah->id, 'kode' => 'operator', 'nama' => 'Operator']);

        $this->actingAs($operator)
            ->delete(route('master-data.jabatan.destroy', $jabatan))
            ->assertRedirect();

        $this->assertSoftDeleted('master_data_jabatan', ['id' => $jabatan->id]);
    }

    public function test_jabatan_sekolah_lain_tidak_bisa_diubah_atau_dihapus(): void
    {
        $sekolahA = $this->sekolah('SMA A');
        $sekolahB = $this->sekolah('SMA B');
        $operatorA = $this->operator($sekolahA);
        $jabatanB = Jabatan::create(['sekolah_id' => $sekolahB->id, 'kode' => 'kepala_sekolah', 'nama' => 'Kepala B']);

        $this->actingAs($operatorA)
            ->put(route('master-data.jabatan.update', $jabatanB), [
                'kode' => 'kepala_sekolah',
                'nama' => 'Diretas',
            ])
            ->assertNotFound();

        $this->actingAs($operatorA)
            ->delete(route('master-data.jabatan.destroy', $jabatanB))
            ->assertNotFound();

        $this->assertSame('Kepala B', $jabatanB->fresh()->nama);
        $this->assertNotSoftDeleted('master_data_jabatan', ['id' => $jabatanB->id]);
    }

    public function test_halaman_pegawai_tidak_lagi_memuat_field_jabatan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Budi']);

        $this->actingAs($operator)
            ->get(route('master-data.pegawai.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pegawai', 1));
    }
}
