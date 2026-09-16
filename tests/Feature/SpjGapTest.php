<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpjGapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN 1 SPJ'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_spj'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function admin(string $username = 'admin_spj'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $user->assignRole('admin_kcd');

        return $user;
    }

    private function spj(Sekolah $sekolah, int $bulan, string $noSpk = 'SPK/01'): Spj
    {
        return activity()->withoutLogging(fn () => Spj::create([
            'sekolah_id' => $sekolah->id,
            'kategori' => 'Peralatan & Mesin',
            'sumber_perolehan' => 'BOS Reguler',
            'bulan_realisasi' => $bulan,
            'no_spk' => $noSpk,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'Unit',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));
    }

    public function test_pilih_bulan_menormalkan_bulan_tidak_valid(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.pilih-bulan', ['bulan' => 13]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PelaporanBm/Spj/PilihBulan'));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.pilih-bulan', ['bulan' => 4]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('bulanAwal', 4));
    }

    public function test_create_ditolak_saat_bulan_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.create', ['bulan' => 5]))
            ->assertRedirect(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertSessionHas('error');
    }

    public function test_edit_spk_menolak_data_tidak_ditemukan(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.edit-spk', ['no_spk' => 'SPK/TIDAKADA', 'bulan' => 5]))
            ->assertRedirect(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertSessionHas('error', 'Data Dokumen SPK tidak ditemukan.');
    }

    public function test_edit_spk_menolak_saat_bulan_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->spj($sekolah, 5, 'SPK/LOCK');

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.edit-spk', ['no_spk' => 'SPK/LOCK', 'bulan' => 5]))
            ->assertRedirect(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertSessionHas('error');
    }

    public function test_edit_spk_menampilkan_data_dan_item(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->spj($sekolah, 5, 'SPK/EDIT');

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.edit-spk', ['no_spk' => 'SPK/EDIT', 'bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/Spj/FormSpk')
                ->where('isEdit', true)
                ->where('spkData.no_spk', 'SPK/EDIT')
                ->has('spkData.items', 1));
    }

    public function test_store_spk_ditolak_saat_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store-spk'), [
                'no_spk' => 'SPK/LOCK',
                'sumber_perolehan' => 'BOS Reguler',
                'bulan_realisasi' => 5,
                'items' => [[
                    'kode_barang' => '1.3.2.01',
                    'nama_barang' => 'Laptop',
                    'jenis_aset' => 'Peralatan dan Mesin',
                    'volume' => 1,
                    'harga_satuan' => 1_000_000,
                ]],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_destroy_operator_tidak_bisa_hapus_spj_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $user = $this->operator($sekolahA);
        $spjLain = $this->spj($sekolahB, 5, 'SPK/B');

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spjLain->id))
            ->assertForbidden();

        $this->assertDatabaseHas('pelaporan_bm_spj', ['id' => $spjLain->id]);
    }

    public function test_destroy_ditolak_saat_bulan_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $spj = $this->spj($sekolah, 5, 'SPK/LOCK');

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pelaporan_bm_spj', ['id' => $spj->id]);
    }

    public function test_destroy_spj_sukses_ikut_hapus_realisasi(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $spj = $this->spj($sekolah, 5, 'SPK/DEL');

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('pelaporan_bm_spj', ['id' => $spj->id]);
        $this->assertDatabaseMissing('pelaporan_bm_realisasi', ['id' => $realisasi->id]);
    }

    public function test_destroy_spk_ditolak_saat_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->spj($sekolah, 5, 'SPK/LOCK');

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK/LOCK', 'bulan' => 5]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pelaporan_bm_spj', ['no_spk' => 'SPK/LOCK']);
    }

    public function test_destroy_spk_sukses_menghapus_semua_item_dan_realisasi(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $a = $this->spj($sekolah, 5, 'SPK/MULTI');
        $b = $this->spj($sekolah, 5, 'SPK/MULTI');

        foreach ([$a, $b] as $item) {
            activity()->withoutLogging(fn () => Realisasi::create([
                'spj_id' => $item->id,
                'sekolah_id' => $sekolah->id,
                'kodering_belanja' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Laptop',
                'volume' => 1,
                'harga_satuan' => 1_000_000,
                'nilai_perolehan' => 1_000_000,
            ]));
        }

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK/MULTI', 'bulan' => 5]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
        $this->assertDatabaseCount('pelaporan_bm_realisasi', 0);
    }

    public function test_update_spj_operator_lain_ditolak(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $user = $this->operator($sekolahA);
        $spjLain = $this->spj($sekolahB, 5, 'SPK/B');

        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spjLain->id), [
                'no_spk' => 'SPK/B',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'X',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 1_000_000,
            ])
            ->assertForbidden();
    }

    public function test_update_spj_menolak_acuan_lintas_sekolah(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $user = $this->operator($sekolahA);
        $spj = $this->spj($sekolahA, 5, 'SPK/A');

        $acuanLain = activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolahB->id,
            'tanggal' => now()->toDateString(),
            'kodering' => '5.2.02.01',
            'uraian' => 'Acuan B',
            'nominal' => 1_000_000,
            'bulan' => 5,
        ]));

        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spj->id), [
                'no_spk' => 'SPK/A',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Laptop',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 1_000_000,
                'acuan_id' => $acuanLain->id,
            ])
            ->assertSessionHas('error', 'Acuan tidak valid untuk sekolah ini.');

        $this->assertNull($spj->fresh()->acuan_id);
    }

    public function test_update_spj_sukses_menghitung_nilai_perolehan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $spj = $this->spj($sekolah, 5, 'SPK/A');

        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spj->id), [
                'no_spk' => 'SPK/A',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Laptop Baru',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 3,
                'harga_satuan' => 2_000_000,
            ])
            ->assertSessionHas('success');

        $fresh = $spj->fresh();
        $this->assertEquals('Laptop Baru', $fresh->nama_barang);
        $this->assertEquals(6_000_000, (float) $fresh->nilai_perolehan);
    }

    public function test_cari_barang_query_kosong_mengembalikan_array_kosong(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->getJson(route('pelaporan-bm.cari-barang', ['q' => '   ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_cari_barang_memprioritaskan_master_lalu_fallback_ke_spj(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        KodeBarang::create([
            'kode_barang' => '1.3.2.05.01',
            'uraian' => 'Laptop Master',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'Unit',
        ]);

        $hasilMaster = $this->actingAs($user)
            ->getJson(route('pelaporan-bm.cari-barang', ['q' => 'laptop']))
            ->assertOk()
            ->json();
        $this->assertCount(1, $hasilMaster);
        $this->assertEquals('1.3.2.05.01', $hasilMaster[0]['kode_barang']);

        // Tidak ada di master -> fallback ke SPJ sekolah sendiri
        $this->spj($sekolah, 5, 'SPK/FB');
        $hasilFallback = $this->actingAs($user)
            ->getJson(route('pelaporan-bm.cari-barang', ['q' => '1.3.2.01']))
            ->assertOk()
            ->json();
        $this->assertNotEmpty($hasilFallback);
    }
}
