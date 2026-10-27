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

class MasterDataPenugasanHttpTest extends TestCase
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

    private function operator(Sekolah $sekolah, string $username = 'op.penugasan'): User
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

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return $override + [
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'pangkat_golongan' => 'IV/a',
            'jabatan' => 'kepala_sekolah',
            'jenis_penugasan' => 'definitif',
            'mulai_menjabat' => '2026-01-01',
        ];
    }

    public function test_operator_dapat_membuka_halaman_penugasan(): void
    {
        $sekolah = $this->sekolah();

        $this->actingAs($this->operator($sekolah))
            ->get(route('master-data.penugasan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MasterData/Penugasan/Index'));
    }

    public function test_simpan_penugasan_membuat_pegawai_jabatan_dan_periode(): void
    {
        $sekolah = $this->sekolah();

        $this->actingAs($this->operator($sekolah))
            ->post(route('master-data.penugasan.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Pegawai::count());
        $this->assertSame(1, Jabatan::count());
        $this->assertSame(1, PenugasanPegawai::count());
    }

    public function test_plt_menutup_penugasan_definitif_sebelumnya(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)->post(route('master-data.penugasan.store'), $this->payload([
            'mulai_menjabat' => '2024-01-01',
        ]))->assertSessionHas('success');

        $this->actingAs($operator)->post(route('master-data.penugasan.store'), $this->payload([
            'nama' => 'Citra Dewi',
            'nip' => '199002022010012002',
            'jenis_penugasan' => 'plt',
            'mulai_menjabat' => '2025-03-11',
        ]))->assertSessionHas('success');

        $lama = PenugasanPegawai::whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'Budi Santoso'))->firstOrFail();
        $baru = PenugasanPegawai::whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'Citra Dewi'))->firstOrFail();

        $this->assertSame('2025-03-10', $lama->selesai_menjabat->format('Y-m-d'));
        $this->assertNull($baru->selesai_menjabat);
        $this->assertSame('plt', $baru->jenis_penugasan);
    }

    public function test_akhiri_dan_mutasi_lewat_http(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)->post(route('master-data.penugasan.store'), $this->payload([
            'jabatan' => 'wakasek_sarpras',
        ]))->assertSessionHas('success');

        $penugasan = PenugasanPegawai::firstOrFail();

        $this->actingAs($operator)
            ->put(route('master-data.penugasan.mutasi', $penugasan), [
                'jabatan' => 'kepala_sekolah',
                'jenis_penugasan' => 'definitif',
                'mulai_menjabat' => '2026-07-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-06-30', $penugasan->fresh()->selesai_menjabat->format('Y-m-d'));

        $baru = PenugasanPegawai::where('jabatan_id', '!=', $penugasan->jabatan_id)->firstOrFail();

        $this->actingAs($operator)
            ->put(route('master-data.penugasan.akhiri', $baru), ['selesai_menjabat' => '2026-12-31'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-12-31', $baru->fresh()->selesai_menjabat->format('Y-m-d'));
    }

    public function test_periode_tumpang_tindih_kembali_sebagai_error_flash(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)->post(route('master-data.penugasan.store'), $this->payload([
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => '2026-06-30',
        ]))->assertSessionHas('success');

        $this->actingAs($operator)->post(route('master-data.penugasan.store'), $this->payload([
            'nama' => 'Citra Dewi',
            'nip' => '199002022010012002',
            'mulai_menjabat' => '2026-05-01',
            'selesai_menjabat' => '2026-12-31',
        ]))->assertSessionHas('error');
    }

    public function test_sekolah_lain_tidak_bisa_mengubah_penugasan(): void
    {
        $sekolahA = $this->sekolah('SMA A');
        $sekolahB = $this->sekolah('SMA B');
        $operatorA = $this->operator($sekolahA, 'op.a');
        $operatorB = $this->operator($sekolahB, 'op.b');

        $this->actingAs($operatorB)->post(route('master-data.penugasan.store'), $this->payload([
            'nama' => 'Pegawai B',
            'nip' => '197001011990011001',
        ]))->assertSessionHas('success');

        $penugasanB = PenugasanPegawai::firstOrFail();

        $this->actingAs($operatorA)
            ->put(route('master-data.penugasan.update', $penugasanB), $this->payload(['nama' => 'Diretas']))
            ->assertNotFound();

        $this->actingAs($operatorA)
            ->put(route('master-data.penugasan.akhiri', $penugasanB), ['selesai_menjabat' => '2026-12-31'])
            ->assertNotFound();

        $this->actingAs($operatorA)
            ->put(route('master-data.penugasan.mutasi', $penugasanB), [
                'jabatan' => 'kepala_sekolah',
                'mulai_menjabat' => '2026-07-01',
            ])
            ->assertNotFound();

        $this->assertSame('Pegawai B', $penugasanB->fresh()->pegawai->nama_lengkap);
        $this->assertNull($penugasanB->fresh()->selesai_menjabat);
    }

    public function test_pengguna_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create([
            'name' => 'Divisi',
            'username' => 'divisi.penugasan',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_divisi');

        $this->actingAs($user)
            ->get(route('master-data.penugasan.index'))
            ->assertForbidden();
    }

    public function test_admin_tanpa_konteks_sekolah_ditolak_di_halaman_master(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin.tanpa.sekolah',
            'password' => bcrypt('password'),
            'sekolah_id' => null,
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('admin_kcd');

        foreach (['master-data.penugasan.index', 'master-data.jabatan.index'] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertStatus(422);
        }
    }
}
