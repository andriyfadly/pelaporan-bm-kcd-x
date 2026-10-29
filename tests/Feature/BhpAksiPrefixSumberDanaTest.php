<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman BOPD kini memposting ke route ber-prefix (`/distribusi-bhp/bopd/...`).
 * Sebelumnya beberapa aksi memakai route flat yang default ke BOS, sehingga
 * record BOPD ditolak validasi ("id invalid") atau gagal dengan pesan
 * "Sumber dana tidak sesuai" — fitur tampak rusak hanya untuk BOPD.
 */
class BhpAksiPrefixSumberDanaTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN AKSI',
            'kota_kab' => 'KOTA BANDUNG',
            'npsn' => '77778888',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Aksi',
            'username' => 'adm.aksi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->admin->assignRole('admin_kcd');

        $this->operator = User::create([
            'name' => 'Operator Aksi',
            'username' => 'op.aksi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');
    }

    private function draftBopd(): Pengajuan
    {
        return SumberDana::dengan('BOPD', fn (): Pengajuan => Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'tanggal' => '2026-06-01',
            'status' => 'draft',
        ]));
    }

    public function test_destroy_draft_bopd_lewat_route_berprefix(): void
    {
        $pengajuan = $this->draftBopd();

        $this->actingAs($this->operator)
            ->delete("/distribusi-bhp/bopd/pengajuan/{$pengajuan->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, SumberDana::dengan('BOPD', fn (): int => Pengajuan::count()));
    }

    public function test_destroy_draft_bopd_lewat_route_flat(): void
    {
        // Route flat tidak membawa prefix dana. Tanpa default dari record,
        // sumber dana request jatuh ke BOS dan draft BOPD tidak pernah terhapus.
        $pengajuan = $this->draftBopd();

        $this->actingAs($this->operator)
            ->delete("/distribusi-bhp/pengajuan/{$pengajuan->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, SumberDana::dengan('BOPD', fn (): int => Pengajuan::count()));
    }

    public function test_destroy_tetap_menolak_sumber_dana_yang_diklaim_salah(): void
    {
        $pengajuan = $this->draftBopd();

        // Prefix BOS hanya mencari di tabel BOS, jadi record BOPD tidak
        // ditemukan — permintaan tidak boleh menebak-nebak dana lain.
        $this->actingAs($this->operator)
            ->delete("/distribusi-bhp/bos/pengajuan/{$pengajuan->id}")
            ->assertNotFound();

        $this->assertSame(1, SumberDana::dengan('BOPD', fn (): int => Pengajuan::count()));
    }

    public function test_batch_assign_bopd_lewat_route_berprefix(): void
    {
        $bopd = SumberDana::dengan('BOPD', fn (): KodeRekening => KodeRekening::create([
            'kode' => '5.1.02.01.01.9001',
            'nama' => 'Kertas BOPD',
        ]));

        $this->actingAs($this->admin)
            ->post('/distribusi-bhp/bopd/master/kode-rekening/batch-assign', [
                'assignments' => [['id' => $bopd->id, 'kode_barang' => '1.1.7.01.01']],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            '1.1.7.01.01',
            SumberDana::dengan('BOPD', fn (): ?string => $bopd->fresh()->kode_barang),
        );
    }

    public function test_batch_assign_bopd_lewat_route_flat_dengan_sumber_dana(): void
    {
        // Inilah yang dikirim halaman BOPD: route flat + `sumber_dana` eksplisit.
        // Tanpa membaca input itu, `exists` menunjuk tabel BOS dan id BOPD ditolak.
        $bopd = SumberDana::dengan('BOPD', fn (): KodeRekening => KodeRekening::create([
            'kode' => '5.1.02.01.01.9002',
            'nama' => 'Tinta BOPD',
        ]));

        $this->actingAs($this->admin)
            ->post('/distribusi-bhp/master/kode-rekening/batch-assign', [
                'sumber_dana' => 'BOPD',
                'assignments' => [['id' => $bopd->id, 'kode_barang' => '1.1.7.01.04']],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            '1.1.7.01.04',
            SumberDana::dengan('BOPD', fn (): ?string => $bopd->fresh()->kode_barang),
        );
    }

    public function test_batch_assign_tidak_bocor_ke_sumber_dana_lain(): void
    {
        $bos = SumberDana::dengan('BOS', fn (): KodeRekening => KodeRekening::create([
            'kode' => '5.1.02.01.01.9003',
            'nama' => 'Kertas BOS',
        ]));

        // id BOS dikirim sebagai BOPD: harus ditolak, bukan diam-diam di-update.
        $this->actingAs($this->admin)
            ->post('/distribusi-bhp/master/kode-rekening/batch-assign', [
                'sumber_dana' => 'BOPD',
                'assignments' => [['id' => $bos->id, 'kode_barang' => '1.1.7.01.01']],
            ])
            ->assertSessionHasErrors('assignments.0.id');

        $this->assertNull(SumberDana::dengan('BOS', fn (): ?string => $bos->fresh()->kode_barang));
    }
}
