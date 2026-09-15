<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PelaporanBmTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_and_redirect_to_dashboard(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'username' => 'admintest',
            'password' => 'password',
        ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_access_pelaporan_bm_pages(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator1',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.acuan.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.cetak'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.realisasi.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.realisasi.unduh'))
            ->assertOk();

        // Admin without sekolah_id accessing cetak
        $admin = User::create([
            'name' => 'Admin KCD Cetak',
            'username' => 'admin_cetak',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin)
            ->get(route('pelaporan-bm.cetak'))
            ->assertOk();

        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Cetak',
            'kota_kab' => 'Kota Cirebon',
        ]);

        Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 5,
            'ba_tgl' => '2026-05-10',
            'no_spk' => 'SPK/CTK/01',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Cetak',
            'volume' => 1,
            'harga_satuan' => 15000000,
            'nilai_perolehan' => 15000000,
        ]);

        $this->actingAs($admin)
            ->postJson(route('pelaporan-bm.cetak.check'), ['bulan' => 5, 'tahun' => 2026])
            ->assertOk()
            ->assertJson(['total_rows' => 1]);

        $response = $this->actingAs($admin)
            ->get(route('pelaporan-bm.cetak.unduh', ['bulan' => 5, 'tahun' => 2026]));
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Laptop Cetak', $response->streamedContent());
    }

    public function test_user_can_update_and_export_spj_and_kirim_laporan(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator2',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        $spj = Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK/001',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Core i7',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 2,
            'harga_satuan' => 15000000,
            'nilai_perolehan' => 30000000,
        ]);

        // Test update SPJ
        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spj), [
                'no_spk' => 'SPK/001-REV',
                'bulan_realisasi' => 5,
                'kode_barang' => '1.3.2.05',
                'nama_barang' => 'Laptop Core i7 Gen 13',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 2,
                'harga_satuan' => 16000000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_spj', [
            'id' => $spj->id,
            'no_spk' => 'SPK/001-REV',
            'nilai_perolehan' => 32000000,
        ]);

        // Test kirim laporan via input-realisasi (validasi balance: acuan belum terpenuhi)
        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.kirim-laporan'), [
                'bulan_realisasi' => 5,
            ])
            ->assertSessionHas('error');

        // Test unduh rekap
        $response = $this->actingAs($user)
            ->get(route('pelaporan-bm.unduh', ['bulan' => 5]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('SPK/001-REV', $content);

        // Test import acuan CSV
        $csvContent = "Tanggal,Kodering,BKU,Uraian,Nominal\n2026-05-10,5.2.02.05,BKU-01,Komputer Server,25000000\n";
        $file = UploadedFile::fake()->createWithContent('acuan.csv', $csvContent);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.acuan.import'), [
                'file' => $file,
                'bulan' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_acuan', [
            'sekolah_id' => $sekolah->id,
            'uraian' => 'Komputer Server',
            'nominal' => 25000000,
        ]);

        // Test destroy all acuan for month 5
        $this->actingAs($user)
            ->post(route('pelaporan-bm.acuan.destroy-all'), [
                'bulan' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_acuan', [
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
        ]);
    }

    public function test_user_can_search_barang_and_delete_spk_and_items(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator3',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        $spj1 = Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK-BATCH-01',
            'bulan_realisasi' => 5,
            'kategori' => 'Buku',
            'no_sertifikat' => 'ISBN-978-001',
            'kode_barang' => '1.3.5.01',
            'nama_barang' => 'Buku Ensiklopedia Sains',
            'jenis_aset' => 'Aset Tetap Lainnya',
            'volume' => 10,
            'harga_satuan' => 100000,
            'nilai_perolehan' => 1000000,
        ]);

        $spj2 = Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK-BATCH-01',
            'bulan_realisasi' => 5,
            'kategori' => 'Buku',
            'no_sertifikat' => 'ISBN-978-002',
            'kode_barang' => '1.3.5.02',
            'nama_barang' => 'Buku Ensiklopedia Fisika',
            'jenis_aset' => 'Aset Tetap Lainnya',
            'volume' => 5,
            'harga_satuan' => 120000,
            'nilai_perolehan' => 600000,
        ]);

        // Autocomplete search test
        $this->actingAs($user)
            ->getJson('/pelaporan-bm/cari-barang?q=Sains')
            ->assertOk()
            ->assertJsonFragment(['nama_barang' => 'Buku Ensiklopedia Sains']);

        $this->actingAs($user)
            ->getJson('/pelaporan-bm/cari-barang?q=a')
            ->assertOk()
            ->assertJson([]);

        // Delete single item
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj2))
            ->assertRedirect();
        $this->assertDatabaseMissing('pelaporan_bm_spj', ['id' => $spj2->id]);

        // Delete entire SPK document test
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK-BATCH-01', 'bulan' => 5]))
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_spj', [
            'no_spk' => 'SPK-BATCH-01',
        ]);
    }

    public function test_spj_store_and_locked_guards(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator4',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        // Store SPJ
        $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store'), [
                'no_spk' => 'SPK/999',
                'bulan_realisasi' => 6,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Printer Epson',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 3500000,
            ])
            ->assertRedirect();

        $spj = Spj::where('no_spk', 'SPK/999')->first();
        $this->assertNotNull($spj);
        $this->assertEquals(3500000, $spj->nilai_perolehan);

        // Lock month 6
        KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => '6',
            'status_kunci' => true,
        ]);

        // Attempt store when locked
        $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store'), [
                'no_spk' => 'SPK/1000',
                'bulan_realisasi' => 6,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Printer Baru',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 3000000,
            ])
            ->assertSessionHas('error');

        // Attempt update when locked
        $this->actingAs($user)
            ->put(route('pelaporan-bm.spj.update', $spj), [
                'no_spk' => 'SPK/999-EDIT',
                'bulan_realisasi' => 6,
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Printer Epson Edited',
                'jenis_aset' => 'Peralatan dan Mesin',
                'volume' => 1,
                'harga_satuan' => 3500000,
            ])
            ->assertSessionHas('error');

        // Attempt destroy single item when locked
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy', $spj))
            ->assertSessionHas('error');

        // Attempt destroy SPK when locked
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.spj.destroy-spk', ['no_spk' => 'SPK/999', 'bulan' => 6]))
            ->assertSessionHas('error');
    }

    public function test_acuan_store_and_destroy(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator5',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        // Store Acuan
        $this->actingAs($user)
            ->post(route('pelaporan-bm.acuan.store'), [
                'tanggal' => '2026-06-01',
                'kodering' => '5.2.02.05',
                'uraian' => 'Acuan Meja Guru',
                'nominal' => 5000000,
                'bulan' => 6,
            ])
            ->assertRedirect();

        $acuan = Acuan::where('uraian', 'Acuan Meja Guru')->first();
        $this->assertNotNull($acuan);

        // Destroy Acuan
        $this->actingAs($user)
            ->delete(route('pelaporan-bm.acuan.destroy', $acuan))
            ->assertRedirect();

        $this->assertDatabaseMissing('pelaporan_bm_acuan', ['id' => $acuan->id]);
    }

    public function test_realisasi_index_and_unduh_with_filters(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'username' => 'operator6',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.06.01.0001',
            'bulan_realisasi' => '7',
            'no_spk' => 'SPK-REAL-01',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Monitor LED 24 Inch',
            'jenis_aset' => 'Peralatan dan Mesin',
            'ba_tgl' => '2026-07-15',
            'volume' => 3,
            'harga_satuan' => 2000000,
            'nilai_perolehan' => 6000000,
        ]);

        // Index with filters
        $this->actingAs($user)
            ->get(route('pelaporan-bm.realisasi.index', [
                'filter_barang' => 'Monitor',
                'filter_bulan' => 7,
                'filter_tahun' => 2026,
            ]))
            ->assertOk();

        // Unduh with filters
        $response = $this->actingAs($user)
            ->get(route('pelaporan-bm.realisasi.unduh', [
                'filter_barang' => 'Monitor',
                'filter_bulan' => 7,
                'filter_tahun' => 2026,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Monitor LED 24 Inch', $content);
    }

    public function test_multi_item_spk_and_input_realisasi_workflow(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 2 Alur',
            'kota_kab' => 'Kota Bandung',
        ]);

        $user = User::create([
            'name' => 'Operator 2',
            'username' => 'operator2',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
        ]);

        $acuan = Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 20000000,
            'bulan' => 8,
            'uraian' => 'Pengadaan Laptop Sekolah',
        ]);

        // 1. Akses form create SPK
        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.create', ['kategori' => 'Peralatan & Mesin', 'bulan' => 8]))
            ->assertOk();

        // 2. Simpan Dokumen SPK dengan multiple items
        $storeResponse = $this->actingAs($user)
            ->post(route('pelaporan-bm.spj.store-spk'), [
                'no_spk' => 'SPK/MULTI/01',
                'no_sp2d' => '001/SP2D/2026',
                'sumber_perolehan' => 'BOS Reguler',
                'bulan_realisasi' => 8,
                'kategori' => 'Peralatan & Mesin',
                'items' => [
                    [
                        'kode_barang' => '1.3.2.05',
                        'nama_barang' => 'Laptop A',
                        'jenis_aset' => 'Peralatan dan Mesin',
                        'volume' => 1,
                        'harga_satuan' => 10000000,
                    ],
                    [
                        'kode_barang' => '1.3.2.05',
                        'nama_barang' => 'Laptop B',
                        'jenis_aset' => 'Peralatan dan Mesin',
                        'volume' => 1,
                        'harga_satuan' => 10000000,
                    ],
                ],
            ]);

        $storeResponse->assertRedirect(route('pelaporan-bm.spj.index', ['bulan' => 8]));
        $this->assertDatabaseCount('pelaporan_bm_spj', 2);

        // 3. Akses form edit SPK
        $this->actingAs($user)
            ->get(route('pelaporan-bm.spj.edit-spk', ['no_spk' => 'SPK/MULTI/01', 'bulan' => 8]))
            ->assertOk();

        // 4. Akses Input Realisasi index & tambah
        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 8]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.tambah', ['kodering' => '5.2.02.05.01.0001', 'bulan_realisasi' => 8]))
            ->assertOk();

        // 5. Alokasikan barang SPJ ke Kodering Realisasi
        $spjList = Spj::where('sekolah_id', $sekolah->id)->pluck('id')->all();
        $simpanRealResponse = $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.05.01.0001',
                'bulan_realisasi' => 8,
                'item_ids' => $spjList,
            ]);

        $simpanRealResponse->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 8]));
        $this->assertDatabaseCount('pelaporan_bm_realisasi', 2);

        // 6. Akses Edit Realisasi & Batalkan uncheck 1 item
        $realisasiItem = Realisasi::where('sekolah_id', $sekolah->id)->first();
        $updateRealResponse = $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.update'), [
                'kodering' => '5.2.02.05.01.0001',
                'bulan_realisasi' => 8,
                'uncheck_ids' => [$realisasiItem->id],
            ]);

        $updateRealResponse->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 8]));
        $this->assertDatabaseCount('pelaporan_bm_realisasi', 1);
        $this->assertFalse(Realisasi::where('id', $realisasiItem->id)->exists());
    }
}
