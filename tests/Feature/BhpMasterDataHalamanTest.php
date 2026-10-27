<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master data BHP yang dipakai alur penerimaan: daftar & CRUD penyedia serta
 * daftar & assign kode rekening. Sebelumnya hanya jalur `store` yang diuji.
 */
class BhpMasterDataHalamanTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Master', 'kota_kab' => 'Kota Bandung']);

        $this->user = User::create([
            'name' => 'Operator',
            'username' => 'op.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->user->assignRole('operator_sekolah');
    }

    public function test_halaman_penyedia_menampilkan_daftar(): void
    {
        Penyedia::create(['nama' => 'CV Alpha', 'alamat' => 'Jl. Satu']);
        Penyedia::create(['nama' => 'CV Beta']);

        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.penyedia.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Master/Penyedia/Index')
                ->has('items.data', 2)
                ->where('items.data.0.nama', 'CV Alpha'));
    }

    public function test_penyedia_dapat_diubah(): void
    {
        $penyedia = Penyedia::create(['nama' => 'CV Lama']);

        $this->actingAs($this->user)
            ->put(route('distribusi-bhp.penyedia.update', $penyedia), [
                'nama' => 'CV Baru',
                'telepon' => '08123456789',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('CV Baru', $penyedia->fresh()->nama);
        $this->assertSame('08123456789', $penyedia->fresh()->telepon);
    }

    public function test_penyedia_ditolak_dihapus_bila_dipakai_penerimaan(): void
    {
        $penyedia = Penyedia::create(['nama' => 'CV Terpakai']);
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'penyedia_id' => $penyedia->id,
        ]);
        $barang = Barang::create([
            'kode_barang' => '1.1.7.30.300',
            'nama_barang' => 'Map',
            'satuan' => 'Buah',
            'sumber_dana' => 'BOS',
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => 1,
            'harga_satuan' => 1_000,
            'nilai_total' => 1_000,
        ]);

        $this->actingAs($this->user)
            ->delete(route('distribusi-bhp.penyedia.destroy', $penyedia))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bhp_penyedia', ['id' => $penyedia->id]);
    }

    public function test_penyedia_tanpa_penerimaan_dapat_dihapus(): void
    {
        $penyedia = Penyedia::create(['nama' => 'CV Lepas']);

        $this->actingAs($this->user)
            ->delete(route('distribusi-bhp.penyedia.destroy', $penyedia))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bhp_penyedia', ['id' => $penyedia->id]);
    }

    public function test_halaman_kode_rekening_menampilkan_daftar_dan_opsi_kode_barang(): void
    {
        KodeRekening::create(['kode' => '5.1.02.01.01.0002', 'nama' => 'Alat Tulis']);

        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.kode-rekening.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Master/KodeRekening/Index')
                ->has('items.data', 1)
                ->has('kodeBarangOptions'));
    }

    /**
     * `assign-kode-rekening-bhp` hanya dimiliki admin_kcd; dengan sekolah_id
     * terisi, resolveSekolahId tetap mengunci ke sekolah sendiri.
     */
    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $admin->assignRole('admin_kcd');

        return $admin;
    }

    public function test_batch_assign_menyimpan_kode_barang(): void
    {
        $a = KodeRekening::create(['kode' => '5.1.02.01.01.0003', 'nama' => 'Kertas']);
        $b = KodeRekening::create(['kode' => '5.1.02.01.01.0004', 'nama' => 'Tinta']);

        $this->actingAs($this->admin())
            ->post(route('distribusi-bhp.kode-rekening.batch-assign'), [
                'assignments' => [
                    ['id' => $a->id, 'kode_barang' => '1.1.7.01.01', 'uraian_kode_barang' => 'Alat Tulis Kantor'],
                    ['id' => $b->id, 'kode_barang' => '1.1.7.01.04'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('1.1.7.01.01', $a->fresh()->kode_barang);
        $this->assertSame('1.1.7.01.04', $b->fresh()->kode_barang);
    }

    public function test_batch_assign_menolak_kode_barang_asing(): void
    {
        $a = KodeRekening::create(['kode' => '5.1.02.01.01.0005', 'nama' => 'Pulpen']);

        $this->actingAs($this->admin())
            ->post(route('distribusi-bhp.kode-rekening.batch-assign'), [
                'assignments' => [
                    ['id' => $a->id, 'kode_barang' => '9.9.9.9.9'],
                ],
            ])
            ->assertSessionHasErrors('assignments.0.kode_barang');

        $this->assertNull($a->fresh()->kode_barang);
    }

    public function test_batch_assign_menolak_id_asing(): void
    {
        $this->actingAs($this->admin())
            ->post(route('distribusi-bhp.kode-rekening.batch-assign'), [
                'assignments' => [
                    ['id' => '00000000-0000-0000-0000-000000000000', 'kode_barang' => '1.1.7.01.01'],
                ],
            ])
            ->assertSessionHasErrors('assignments.0.id');
    }
}
