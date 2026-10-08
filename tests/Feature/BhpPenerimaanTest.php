<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\DistribusiBhp\PengajuanDetail;
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

    public function test_saldo_awal_wajib_memiliki_periode_tahun(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'is_saldo_awal' => true,
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors('periode_tahun');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_penerimaan_biasa_tidak_boleh_memiliki_periode_tahun(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors('periode_tahun');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_saldo_awal_duplikat_per_barang_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $payload = [
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
            'details' => [
                ['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 45000],
            ],
        ];

        $this->actingAs($operator)->post(route('distribusi-bhp.penerimaan.store'), $payload)->assertRedirect();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), $payload)
            ->assertSessionHasErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_saldo_awal_bos_dan_bopd_tidak_saling_menghalangi(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barangBos = $this->barang($sekolah, 'BOS');
        $barangBopd = $this->barang($sekolah, 'BOPD');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'is_saldo_awal' => true,
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barangBos->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOPD',
                'is_saldo_awal' => true,
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barangBopd->id, 'jumlah' => 5, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, Penerimaan::count());
    }

    public function test_saldo_awal_dengan_jumlah_nol_ditolak(): void
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
                    ['barang_id' => $barang->id, 'jumlah' => 0, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_saldo_awal_duplikat_dalam_satu_request_ditolak(): void
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
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                    ['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_operator_sekolah_lain_tidak_melihat_penerimaan(): void
    {
        $sekolahA = $this->sekolah();
        $sekolahB = Sekolah::create(['nama_sekolah' => 'SMKN Lain', 'kota_kab' => 'Kota Cimahi']);
        $operatorA = $this->operator($sekolahA);
        $barang = $this->barang($sekolahA);

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $operatorB = User::create([
            'name' => 'Operator B',
            'username' => 'op.terima.b',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolahB->id,
            'password_changed_at' => now(),
        ]);
        $operatorB->assignRole('operator_sekolah');

        $this->actingAs($operatorB)
            ->get(route('distribusi-bhp.penerimaan.index', ['sumber_dana' => 'BOS']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 0));
    }

    public function test_saldo_awal_bisa_dihapus_sebelum_dipakai(): void
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

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.penerimaan.destroy', $penerimaan))
            ->assertRedirect();

        $this->assertSame(0, Penerimaan::count());
        $this->assertSame(0, PenerimaanDetail::count());
        $this->assertSame(0, app(StokService::class)->fisik($barang));
    }

    public function test_saldo_awal_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.del', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
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

        // Pengajuan approved yang memakai barang dari saldo awal.
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-17',
        ]);
        PengajuanDetail::create([
            'bhp_pengajuan_id' => $pengajuan->id,
            'barang_id' => $barang->id,
            'jumlah_diminta' => 5,
            'jumlah_disetujui' => 5,
        ]);

        $penerimaan = Penerimaan::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.penerimaan.destroy', $penerimaan))
            ->assertSessionHasErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_operator_sekolah_lain_tidak_bisa_menghapus_saldo_awal(): void
    {
        $sekolahA = $this->sekolah();
        $sekolahB = Sekolah::create(['nama_sekolah' => 'SMKN Hapus Lain', 'kota_kab' => 'Kota Cimahi']);
        $operatorA = $this->operator($sekolahA);
        $barang = $this->barang($sekolahA);

        $this->actingAs($operatorA)
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

        $operatorB = User::create([
            'name' => 'Operator B',
            'username' => 'op.del.b',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolahB->id,
            'password_changed_at' => now(),
        ]);
        $operatorB->assignRole('operator_sekolah');

        $this->actingAs($operatorB)
            ->delete(route('distribusi-bhp.penerimaan.destroy', $penerimaan))
            ->assertForbidden();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_penerimaan_biasa_tidak_bisa_dihapus_via_flow_saldo_awal(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect();

        $penerimaan = Penerimaan::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.penerimaan.destroy', $penerimaan))
            ->assertSessionHasErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_guest_tidak_bisa_mengakses_penerimaan(): void
    {
        $this->get(route('distribusi-bhp.penerimaan.index'))->assertRedirect(route('login'));
        $this->post(route('distribusi-bhp.penerimaan.store'))->assertRedirect(route('login'));
    }

    public function test_bendahara_tanpa_permission_ditolak_penerimaan(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.terima',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->post(route('distribusi-bhp.penerimaan.store'), ['sumber_dana' => 'BOS'])
            ->assertForbidden();
    }

    public function test_sumber_dana_invalid_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'APBN',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors('sumber_dana');
    }

    public function test_penyedia_invalid_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'penyedia_id' => '00000000-0000-0000-0000-000000000000',
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors('penyedia_id');
    }

    public function test_barang_invalid_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.penerimaan.store'), [
                'sumber_dana' => 'BOS',
                'details' => [
                    ['barang_id' => '00000000-0000-0000-0000-000000000000', 'jumlah' => 10, 'harga_satuan' => 45000],
                ],
            ])
            ->assertSessionHasErrors('details.0.barang_id');

        $this->assertSame(0, Penerimaan::count());
    }
}
