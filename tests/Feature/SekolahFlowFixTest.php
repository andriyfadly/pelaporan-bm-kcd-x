<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SekolahFlowFixTest extends TestCase
{
    use RefreshDatabase;

    private function buatSekolahDanUser(): array
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Audit',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Audit',
            'username' => 'operator_audit',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        return [$sekolah, $user];
    }

    private function buatSpj(Sekolah $sekolah, array $overrides = []): Spj
    {
        return Spj::create(array_merge([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK/AUDIT/01',
            'bulan_realisasi' => 8,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Audit',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 10000000,
            'nilai_perolehan' => 10000000,
        ], $overrides));
    }

    public function test_destroy_spj_menghapus_realisasi_terkait(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        $acuan = Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 10000000,
            'bulan' => 8,
            'uraian' => 'Pengadaan Laptop',
        ]);

        $spj = $this->buatSpj($sekolah);

        Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'acuan_id' => $acuan->id,
            'kodering_belanja' => '5.2.02.05.01.0001',
            'bulan_realisasi' => '8',
            'no_spk' => $spj->no_spk,
            'kode_barang' => $spj->kode_barang,
            'nama_barang' => $spj->nama_barang,
            'volume' => 1,
            'harga_satuan' => 10000000,
            'nilai_perolehan' => 10000000,
        ]);

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj))
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_spj', ['id' => $spj->id]);
        $this->assertDatabaseMissing('pelaporan_bm_realisasi', ['spj_id' => $spj->id]);
    }

    public function test_destroy_spk_menghapus_seluruh_realisasi_terkait(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        $spj1 = $this->buatSpj($sekolah, ['no_spk' => 'SPK/AUDIT/02']);
        $spj2 = $this->buatSpj($sekolah, ['no_spk' => 'SPK/AUDIT/02', 'nama_barang' => 'Laptop Kedua']);

        $spjIds = [$spj1->id, $spj2->id];
        foreach ($spjIds as $index => $id) {
            Realisasi::create([
                'spj_id' => $id,
                'sekolah_id' => $sekolah->id,
                'kodering_belanja' => '5.2.02.05.01.0001',
                'bulan_realisasi' => '8',
                'no_spk' => 'SPK/AUDIT/02',
                'kode_barang' => '1.3.2.05',
                'nama_barang' => 'Laptop '.$index,
                'volume' => 1,
                'harga_satuan' => 10000000,
                'nilai_perolehan' => 10000000,
            ]);
        }

        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK/AUDIT/02', 'bulan' => 8]))
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_spj', ['no_spk' => 'SPK/AUDIT/02']);
        foreach ($spjIds as $id) {
            $this->assertDatabaseMissing('pelaporan_bm_realisasi', ['spj_id' => $id]);
        }
    }

    public function test_store_spk_edit_menghapus_realisasi_item_yang_dibuang(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        $spj1 = $this->buatSpj($sekolah, ['no_spk' => 'SPK/AUDIT/03']);
        $spj2 = $this->buatSpj($sekolah, ['no_spk' => 'SPK/AUDIT/03', 'nama_barang' => 'Laptop Kedua']);

        Realisasi::create([
            'spj_id' => $spj2->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.05.01.0001',
            'bulan_realisasi' => '8',
            'no_spk' => 'SPK/AUDIT/03',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Kedua',
            'volume' => 1,
            'harga_satuan' => 10000000,
            'nilai_perolehan' => 10000000,
        ]);

        // Edit SPK: hanya pertahankan spj1, spj2 dibuang
        $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store-spk'), [
                'is_edit' => true,
                'no_spk_lama' => 'SPK/AUDIT/03',
                'no_spk' => 'SPK/AUDIT/03',
                'sumber_perolehan' => 'BOS Reguler',
                'bulan_realisasi' => 8,
                'items' => [
                    [
                        'id' => $spj1->id,
                        'kode_barang' => '1.3.2.05',
                        'nama_barang' => 'Laptop Audit',
                        'jenis_aset' => 'Peralatan dan Mesin',
                        'volume' => 1,
                        'harga_satuan' => 10000000,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_spj', ['id' => $spj1->id]);
        $this->assertDatabaseMissing('pelaporan_bm_spj', ['id' => $spj2->id]);
        $this->assertDatabaseMissing('pelaporan_bm_realisasi', ['spj_id' => $spj2->id]);
    }

    public function test_mutasi_ditolak_saat_status_menunggu_approval_walau_status_kunci_false(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        $spj = $this->buatSpj($sekolah);

        KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => '8',
            'status_kunci' => false,
            'status_kirim' => 'menunggu_approval',
        ]);

        $payload = [
            'no_spk' => 'SPK/AUDIT/01-EDIT',
            'bulan_realisasi' => 8,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Edit',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 12000000,
        ];

        // Update ditolak
        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spj), $payload)
            ->assertSessionHas('error');

        // Store baru ditolak
        $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store'), $payload)
            ->assertSessionHas('error');

        // Destroy item ditolak
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj))
            ->assertSessionHas('error');

        // Destroy SPK ditolak
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK/AUDIT/01', 'bulan' => 8]))
            ->assertSessionHas('error');

        // Data tetap utuh
        $this->assertDatabaseHas('pelaporan_bm_spj', [
            'id' => $spj->id,
            'no_spk' => 'SPK/AUDIT/01',
        ]);
    }

    public function test_simpan_realisasi_ditolak_jika_melebihi_sisa_anggaran(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 10000000,
            'bulan' => 8,
            'uraian' => 'Pengadaan Laptop',
        ]);

        $spj = $this->buatSpj($sekolah, ['nilai_perolehan' => 15000000, 'harga_satuan' => 15000000]);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.05.01.0001',
                'bulan_realisasi' => 8,
                'item_ids' => [$spj->id],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_realisasi', 0);
    }

    public function test_simpan_realisasi_diterima_jika_dalam_batas_anggaran(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 20000000,
            'bulan' => 8,
            'uraian' => 'Pengadaan Laptop',
        ]);

        $spj = $this->buatSpj($sekolah);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.05.01.0001',
                'bulan_realisasi' => 8,
                'item_ids' => [$spj->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('pelaporan_bm_realisasi', 1);
    }

    public function test_cari_barang_tidak_membocorkan_barang_sekolah_lain(): void
    {
        [$sekolahA, $userA] = $this->buatSekolahDanUser();

        $sekolahB = Sekolah::create([
            'nama_sekolah' => 'SMKN 2 Lain',
            'kota_kab' => 'Kota Cimahi',
        ]);

        $this->buatSpj($sekolahA, ['nama_barang' => 'Laptop Sekolah A']);
        $this->buatSpj($sekolahB, ['nama_barang' => 'Laptop Sekolah B']);

        // Master kode_barang tetap bisa dicari siapa pun
        $this->actingAs($userA)
            ->getJson('/pelaporan-bm/cari-barang?q=Laptop Sekolah B')
            ->assertOk()
            ->assertJson([]);

        $this->actingAs($userA)
            ->getJson('/pelaporan-bm/cari-barang?q=Laptop Sekolah A')
            ->assertOk()
            ->assertJsonFragment(['nama_barang' => 'Laptop Sekolah A']);
    }

    public function test_npsn_sekolah_tampil_di_rekapan(): void
    {
        [$sekolah, $admin] = $this->buatSekolahDanUser();
        $sekolah->update(['npsn' => '20219701']);

        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_rekapan',
            'password' => bcrypt('password'),
        ]);

        $this->buatSpj($sekolah);

        $response = $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 8]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('PelaporanBm/Rekapan/Index')
            ->where('items.0.npsn', '20219701'));
    }

    public function test_cari_barang_realtime_sesuai_legacy(): void
    {
        [$sekolahA, $userA] = $this->buatSekolahDanUser();

        KodeBarang::create([
            'kode_barang' => '1.3.2',
            'uraian' => 'Peralatan Mesin Induk',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'UNIT',
        ]);
        KodeBarang::create([
            'kode_barang' => '1.3.2.05',
            'uraian' => 'Komputer Laptop',
            'jenis_aset' => 'PERSONAL KOMPUTER',
            'satuan' => 'UNIT',
        ]);
        $this->buatSpj($sekolahA, ['nama_barang' => 'Laptop Sekolah A']);

        // Kode induk disembunyikan, hanya leaf yang muncul
        $this->actingAs($userA)
            ->getJson('/pelaporan-bm/cari-barang?q=1.3.2')
            ->assertOk()
            ->assertJsonFragment(['kode_barang' => '1.3.2.05'])
            ->assertJsonMissing(['kode_barang' => '1.3.2']);

        // Pencarian lowercase pada uraian
        $this->actingAs($userA)
            ->getJson('/pelaporan-bm/cari-barang?q=laptop')
            ->assertOk()
            ->assertJsonFragment(['nama_barang' => 'Komputer Laptop']);

        // Fallback riwayat SPJ tetap ter-scoped per sekolah
        $this->actingAs($userA)
            ->getJson('/pelaporan-bm/cari-barang?q=Laptop Sekolah A')
            ->assertOk()
            ->assertJsonFragment(['nama_barang' => 'Laptop Sekolah A']);
    }

    public function test_endpoint_toggle_realisasi_dan_kirim_laporan_sudah_dihapus(): void
    {
        [$sekolah, $user] = $this->buatSekolahDanUser();

        $spj = $this->buatSpj($sekolah);

        $this->actingAs($user)
            ->post('/pelaporan-bm/spj/'.$spj->id.'/toggle-realisasi')
            ->assertNotFound();

        $this->actingAs($user)
            ->post('/pelaporan-bm/kirim-laporan', ['bulan' => 8])
            ->assertNotFound();
    }
}
