<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BhpDomainBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bhp_tables_exist_and_are_separate_from_pelaporan_bm(): void
    {
        foreach ([
            'bhp_kode_rekening',
            'bhp_barang',
            'bhp_harga_barang',
            'bhp_penyedia',
            'bhp_penerimaan',
            'bhp_penerimaan_detail',
            'bhp_pengajuan',
            'bhp_pengajuan_detail',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} harus ada.");
        }

        $this->assertTrue(Schema::hasTable('bhp_barang'));
        $this->assertFalse(Schema::hasColumn('bhp_barang', 'stok_tersedia'), 'Stok tidak boleh disimpan sebagai kolom denormal.');
    }

    public function test_kode_rekening_unique_per_sumber_dana(): void
    {
        KodeRekening::create(['kode' => '5.1.02.01.01.0001', 'nama' => 'Bahan Bangunan', 'sumber_dana' => 'BOS']);
        KodeRekening::create(['kode' => '5.1.02.01.01.0001', 'nama' => 'Bahan Bangunan', 'sumber_dana' => 'BOPD']);

        $this->assertSame(2, KodeRekening::where('kode', '5.1.02.01.01.0001')->count());
    }

    public function test_barang_terikat_sumber_dana(): void
    {
        $rekening = KodeRekening::create([
            'kode' => '5.1.02.01.01.0002',
            'nama' => 'Bahan Kimia',
            'sumber_dana' => 'BOS',
        ]);

        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.01',
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'kode_rekening_id' => $rekening->id,
            'sumber_dana' => 'BOS',
        ]);

        $this->assertSame('BOS', $barang->sumber_dana);
        $this->assertTrue($barang->kodeRekening->is($rekening));
    }

    public function test_active_user_can_open_bhp_dashboard(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);

        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 1 Contoh', 'kota_kab' => 'Kota Bandung']);

        $user = User::create([
            'name' => 'Admin BHP',
            'username' => 'admin.bhp',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.dashboard'))
            ->assertOk();
    }
}
