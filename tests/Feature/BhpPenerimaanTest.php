<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\StokService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpPenerimaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => 'SMKN Penerimaan', 'kota_kab' => 'Kota Bandung']);
    }

    private function operator(Sekolah $sekolah): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.terima',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function barang(Sekolah $sekolah, string $sumberDana = 'BOS'): Barang
    {
        return Barang::create([
            'kode_barang' => '1.1.7.09.'.$sumberDana,
            'nama_barang' => 'Kertas HVS',
            'satuan' => 'Rim',
            'sumber_dana' => $sumberDana,
        ]);
    }

    public function test_operator_dapat_membuat_penerimaan_dengan_detail(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);
        $penyedia = Penyedia::create(['nama' => 'CV Maju']);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'no_nota_invoice' => 'INV-001',
                'no_kwitansi' => 'KW-001',
                'penyedia_id' => $penyedia->id,
                'tanggal_titimangsa' => '2026-06-17',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 50, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $penerimaan = Penerimaan::firstOrFail();
        $this->assertSame($sekolah->id, $penerimaan->sekolah_id);
        $this->assertSame('BOS', $penerimaan->sumber_dana);
        $this->assertCount(1, $penerimaan->details);

        $detail = PenerimaanDetail::firstOrFail();
        $this->assertSame(50, $detail->jumlah);
        $this->assertSame(45000, (int) $detail->harga_satuan);
        $this->assertSame(2250000, (int) $detail->nilai_total);
    }

    public function test_penerimaan_menambah_stok_fisik_yang_dipakai_stok_service(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal_titimangsa' => '2026-06-17',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 80, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(80, app(StokService::class)->fisik($barang));
        $this->assertSame(80, app(StokService::class)->tersedia($barang));
    }

    public function test_penerimaan_menolak_barang_dari_sumber_dana_berbeda(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barangBopd = $this->barang($sekolah, 'BOPD');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'tanggal_titimangsa' => '2026-06-17',
                'details' => [
                    ['barang_id' => $barangBopd->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_saldo_awal_menandai_is_saldo_awal_dan_periode_tahun(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'is_saldo_awal' => true,
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $penerimaan = Penerimaan::firstOrFail();
        $this->assertTrue($penerimaan->is_saldo_awal);
        $this->assertSame(2026, $penerimaan->periode_tahun);
    }

    public function test_detail_kosong_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'details' => [],
            ])
            ->assertSessionHasErrors('details');
    }
}
