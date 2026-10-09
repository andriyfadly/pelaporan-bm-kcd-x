<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => 'SMKN Master', 'kota_kab' => 'Kota Bandung']);
    }

    /**
     * Admin KCD — memegang `kelola-master-bhp` (tulis master kode rekening/barang)
     * sekaligus `kelola-penyedia-bhp`.
     */
    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'username' => 'admin.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah()->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('admin_kcd');

        return $user;
    }

    /** Operator sekolah — hanya `lihat-distribusi-bhp`; tulis master ditolak. */
    private function operator(): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah()->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_admin_dapat_membuat_kode_rekening_bos_dan_bopd(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0001',
                'nama' => 'Bahan Bangunan',
                'sumber_dana' => 'BOS',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0001',
                'nama' => 'Bahan Bangunan',
                'sumber_dana' => 'BOPD',
            ])
            ->assertRedirect();

        // Tabel terpisah per sumber dana: kode yang sama sah ada di BOS dan BOPD.
        $this->assertSame(1, KodeRekening::sumberDana('BOS')->where('kode', '5.1.02.01.01.0001')->count());
        $this->assertSame(1, KodeRekening::sumberDana('BOPD')->where('kode', '5.1.02.01.01.0001')->count());
    }

    public function test_kode_rekening_duplikat_pada_sumber_dana_sama_ditolak(): void
    {
        $admin = $this->admin();

        KodeRekening::create(['kode' => '5.1.02.01.01.0009', 'nama' => 'Bahan A', 'sumber_dana' => 'BOS']);

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0009',
                'nama' => 'Bahan A',
                'sumber_dana' => 'BOS',
            ])
            ->assertSessionHasErrors('kode');
    }

    public function test_sumber_dana_selain_bos_bopd_ditolak(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0010',
                'nama' => 'Bahan B',
                'sumber_dana' => 'APBD',
            ])
            ->assertStatus(422);
    }

    public function test_admin_dapat_membuat_barang_beserta_harga(): void
    {
        $admin = $this->admin();
        $rekening = KodeRekening::create(['kode' => '5.1.02.01.01.0011', 'nama' => 'Bahan C', 'sumber_dana' => 'BOS']);

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.barang.store'), [
                'kode_barang' => '1.1.7.01.01',
                'nama_barang' => 'Semen Portland',
                'satuan' => 'Sak',
                'kode_rekening_id' => $rekening->id,
                'sumber_dana' => 'BOS',
                'harga_satuan' => 50000,
            ])
            ->assertRedirect();

        $barang = Barang::firstOrFail();
        $this->assertSame('BOS', $barang->sumber_dana);
        $this->assertSame(1, HargaBarang::where('barang_id', $barang->id)->count());
        $this->assertSame(50000, (int) HargaBarang::first()->harga_satuan);
    }

    public function test_barang_menolak_rekening_dari_sumber_dana_berbeda(): void
    {
        $admin = $this->admin();
        $rekeningBopd = KodeRekening::create(['kode' => '5.1.02.01.01.0012', 'nama' => 'Bahan D', 'sumber_dana' => 'BOPD']);

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.barang.store'), [
                'kode_barang' => '1.1.7.01.02',
                'nama_barang' => 'Semen BOPD',
                'satuan' => 'Sak',
                'kode_rekening_id' => $rekeningBopd->id,
                'sumber_dana' => 'BOS',
                'harga_satuan' => 50000,
            ])
            ->assertSessionHasErrors('kode_rekening_id');
    }

    public function test_admin_dapat_menambah_dan_mengubah_harga_barang(): void
    {
        $admin = $this->admin();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOS',
        ]);

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.bos.barang.harga.store', $barang), ['sumber_dana' => 'BOS', 'harga_satuan' => 200000])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('distribusi-bhp.bos.barang.harga.store', $barang), ['sumber_dana' => 'BOS', 'harga_satuan' => 220000])
            ->assertRedirect();

        $this->assertSame(2, HargaBarang::where('barang_id', $barang->id)->count());
    }

    public function test_admin_dapat_memperbarui_barang(): void
    {
        $admin = $this->admin();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOS',
        ]);

        $this->actingAs($admin)
            ->put(route('distribusi-bhp.bos.barang.update', $barang), [
                'kode_barang' => '1.1.7.01.04',
                'nama_barang' => 'Kertas HVS A4',
                'satuan' => 'Rim',
                'sumber_dana' => 'BOS',
            ])
            ->assertRedirect();

        $barang->refresh();
        $this->assertSame('Kertas HVS A4', $barang->nama_barang);
        $this->assertSame('1.1.7.01.04', $barang->kode_barang);
    }

    public function test_admin_dapat_menghapus_barang_beserta_harga(): void
    {
        $admin = $this->admin();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'sumber_dana' => 'BOS',
        ]);
        $barang->harga()->create(['harga_satuan' => 100000, 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('distribusi-bhp.bos.barang.destroy', $barang))
            ->assertRedirect();

        $this->assertSame(0, Barang::whereKey($barang->id)->count());
        $this->assertSame(0, HargaBarang::where('barang_id', $barang->id)->count());
    }

    public function test_halaman_kelola_harga_menampilkan_daftar_harga(): void
    {
        $admin = $this->admin();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'sumber_dana' => 'BOS',
        ]);
        $barang->harga()->create(['harga_satuan' => 100000, 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('distribusi-bhp.bos.barang.harga.index', $barang))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Master/Barang/KelolaHarga')
                ->where('barang.id', $barang->id)
                ->has('hargaList', 1));
    }

    public function test_admin_dapat_mengubah_dan_menghapus_harga(): void
    {
        $admin = $this->admin();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'sumber_dana' => 'BOS',
        ]);
        $harga = $barang->harga()->create(['harga_satuan' => 100000, 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->put(route('distribusi-bhp.bos.barang.harga.update', $harga), ['harga_satuan' => 150000])
            ->assertRedirect();
        $this->assertSame(150000, (int) $harga->fresh()->harga_satuan);

        $this->actingAs($admin)
            ->delete(route('distribusi-bhp.bos.barang.harga.destroy', $harga))
            ->assertRedirect();
        $this->assertSame(0, HargaBarang::whereKey($harga->id)->count());
    }

    public function test_operator_sekolah_dapat_memperbarui_barang(): void
    {
        $operator = $this->operator();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'sumber_dana' => 'BOS',
        ]);

        $this->actingAs($operator)
            ->put(route('distribusi-bhp.bos.barang.update', $barang), [
                'nama_barang' => 'Kertas HVS Folio',
                'sumber_dana' => 'BOS',
            ])
            ->assertRedirect();

        $this->assertSame('Kertas HVS Folio', $barang->fresh()->nama_barang);
    }

    public function test_admin_dapat_membuat_penyedia(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('distribusi-bhp.penyedia.store'), ['nama' => 'CV Sumber Rejeki'])
            ->assertRedirect();

        $this->assertSame(1, Penyedia::where('nama', 'CV Sumber Rejeki')->count());
    }

    public function test_operator_sekolah_dapat_mengelola_penyedia(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penyedia.store'), ['nama' => 'CV Sekolah Jaya'])
            ->assertRedirect();

        $this->assertSame(1, Penyedia::where('nama', 'CV Sekolah Jaya')->count());
    }

    public function test_operator_sekolah_dapat_mengelola_barang(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.barang.store'), [
                'kode_barang' => '1.1.7.01.05',
                'nama_barang' => 'Pulpen',
                'sumber_dana' => 'BOS',
                'harga_satuan' => 5000,
            ])
            ->assertRedirect();

        $this->assertSame(1, Barang::where('nama_barang', 'Pulpen')->count());
    }

    public function test_operator_sekolah_tidak_dapat_menulis_master_bhp(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.9998',
                'nama' => 'X',
                'sumber_dana' => 'BOS',
            ])
            ->assertForbidden();
    }

    public function test_user_tanpa_permission_master_bhp_ditolak(): void
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN X', 'kota_kab' => 'Kota Bandung']);
        $user = User::create([
            'name' => 'Tanpa Role',
            'username' => 'norole.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.9999',
                'nama' => 'X',
                'sumber_dana' => 'BOS',
            ])
            ->assertForbidden();
    }
}
