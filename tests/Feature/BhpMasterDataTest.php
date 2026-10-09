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

    private function operator(): User
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Master', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.master',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_operator_dapat_membuat_kode_rekening_bos_dan_bopd(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0001',
                'nama' => 'Bahan Bangunan',
                'sumber_dana' => 'BOS',
            ])
            ->assertRedirect();

        $this->actingAs($operator)
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
        $operator = $this->operator();

        KodeRekening::create(['kode' => '5.1.02.01.01.0009', 'nama' => 'Bahan A', 'sumber_dana' => 'BOS']);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0009',
                'nama' => 'Bahan A',
                'sumber_dana' => 'BOS',
            ])
            ->assertSessionHasErrors('kode');
    }

    public function test_sumber_dana_selain_bos_bopd_ditolak(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.kode-rekening.store'), [
                'kode' => '5.1.02.01.01.0010',
                'nama' => 'Bahan B',
                'sumber_dana' => 'APBD',
            ])
            ->assertStatus(422);
    }

    public function test_operator_dapat_membuat_barang_beserta_harga(): void
    {
        $operator = $this->operator();
        $rekening = KodeRekening::create(['kode' => '5.1.02.01.01.0011', 'nama' => 'Bahan C', 'sumber_dana' => 'BOS']);

        $this->actingAs($operator)
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
        $operator = $this->operator();
        $rekeningBopd = KodeRekening::create(['kode' => '5.1.02.01.01.0012', 'nama' => 'Bahan D', 'sumber_dana' => 'BOPD']);

        $this->actingAs($operator)
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

    public function test_operator_dapat_menambah_dan_mengubah_harga_barang(): void
    {
        $operator = $this->operator();
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.03',
            'nama_barang' => 'Kertas HVS',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOS',
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.barang.harga.store', $barang), ['sumber_dana' => 'BOS', 'harga_satuan' => 200000])
            ->assertRedirect();
        $this->actingAs($operator)
            ->post(route('distribusi-bhp.barang.harga.store', $barang), ['sumber_dana' => 'BOS', 'harga_satuan' => 220000])
            ->assertRedirect();

        $this->assertSame(2, HargaBarang::where('barang_id', $barang->id)->count());
    }

    public function test_operator_dapat_membuat_penyedia(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penyedia.store'), ['nama' => 'CV Sumber Rejeki'])
            ->assertRedirect();

        $this->assertSame(1, Penyedia::where('nama', 'CV Sumber Rejeki')->count());
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
