<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpSaldoAwalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Saldo Awal'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function operator(Sekolah $sekolah, string $username = 'op.saldo'): User
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

    private function barang(string $sumberDana = 'BOS', string $nama = 'Kertas HVS'): Barang
    {
        return Barang::create([
            'kode_barang' => '1.1.7.09.'.$sumberDana,
            'nama_barang' => $nama,
            'satuan' => 'Rim',
            'sumber_dana' => $sumberDana,
        ]);
    }

    public function test_operator_dapat_membuka_halaman_saldo_awal(): void
    {
        $this->actingAs($this->operator($this->sekolah()))
            ->get(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/SaldoAwal/Index')
                ->where('sumberDana', 'BOS')
                ->where('periode', 2026)
                ->has('items', 0)
                ->where('stats.total_nilai', 0)
            );
    }

    public function test_bendahara_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.saldo',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->get(route('distribusi-bhp.saldo-awal.index'))
            ->assertForbidden();
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('distribusi-bhp.saldo-awal.index'))->assertRedirect(route('login'));
    }

    public function test_operator_dapat_menambah_saldo_awal_dan_melihat_statistik(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2026]));

        $this->assertSame(1, Penerimaan::count());
        $this->assertTrue(Penerimaan::firstOrFail()->is_saldo_awal);

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('stats.total_barang', 1)
                ->where('stats.total_unit', 30)
                ->where('stats.total_nilai', 1_350_000)
            );
    }

    public function test_store_saldo_awal_wajib_periode(): void
    {
        $barang = $this->barang();

        $this->actingAs($this->operator($this->sekolah()))
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertSessionHasErrors('periode_tahun');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_store_saldo_awal_menolak_barang_beda_sumber_dana(): void
    {
        $barang = $this->barang('BOPD');

        $this->actingAs($this->operator($this->sekolah()))
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_store_saldo_awal_duplikat_ditolak(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang();

        $payload = [
            'sumber_dana' => 'BOS',
            'periode_tahun' => 2026,
            'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
        ];

        $this->actingAs($operator)->post(route('distribusi-bhp.saldo-awal.store'), $payload)->assertRedirect();
        $this->actingAs($operator)->post(route('distribusi-bhp.saldo-awal.store'), $payload)->assertSessionHasErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_halaman_hanya_menampilkan_saldo_awal_sekolah_sendiri(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operatorA = $this->operator($sekolahA, 'op.aa');
        $operatorB = $this->operator($sekolahB, 'op.bb');
        $barang = $this->barang();

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 7, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        // Operator A melihat 1 baris; operator B melihat 0.
        $this->actingAs($operatorA)
            ->get(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2026]))
            ->assertInertia(fn ($page) => $page->has('items', 1));

        $this->actingAs($operatorB)
            ->get(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2026]))
            ->assertInertia(fn ($page) => $page->has('items', 0));
    }

    public function test_hapus_satu_barang_saldo_awal_mengurangi_stok(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 12, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $detail = PenerimaanDetail::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.saldo-awal.destroy', $detail))
            ->assertRedirect();

        $this->assertSame(0, Penerimaan::count());
        $this->assertSame(0, PenerimaanDetail::count());
    }

    public function test_operator_sekolah_lain_tidak_bisa_menghapus_saldo_awal(): void
    {
        $sekolahA = $this->sekolah('SMKN A2');
        $sekolahB = $this->sekolah('SMKN B2');
        $operatorA = $this->operator($sekolahA, 'op.a2');
        $barang = $this->barang();

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 4, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $detail = PenerimaanDetail::firstOrFail();

        $this->actingAs($this->operator($sekolahB, 'op.b2'))
            ->delete(route('distribusi-bhp.saldo-awal.destroy', $detail))
            ->assertForbidden();

        $this->assertSame(1, PenerimaanDetail::count());
    }

    public function test_periode_menentukan_baris_yang_tampil(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang();

        foreach ([2025, 2026] as $tahun) {
            $this->actingAs($operator)
                ->post(route('distribusi-bhp.saldo-awal.store'), [
                    'sumber_dana' => 'BOS',
                    'periode_tahun' => $tahun,
                    'details' => [['barang_id' => $barang->id, 'jumlah' => 1, 'harga_satuan' => 1000]],
                ])
                ->assertRedirect();
        }

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.saldo-awal.index', ['sumber_dana' => 'BOS', 'periode' => 2025]))
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('availablePeriods', fn ($periods) => collect($periods)->contains(2026))
            );
    }
}
