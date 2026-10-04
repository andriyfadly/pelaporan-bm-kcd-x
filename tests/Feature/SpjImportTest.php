<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpjImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama): Sekolah
    {
        // NPSN default ada di daftar allowlist import SPJ; test yang menolak
        // NPSN lain membuat sekolahnya sendiri dengan NPSN di luar daftar.
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_spj_import'): User
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

    private function buatKatalog(): void
    {
        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '5.2.02.01.004',
            'uraian' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'UNIT',
        ]));
        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '5.2.02.02.001',
            'uraian' => 'Kursi',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'BUAH',
        ]));
    }

    /**
     * Bangun file .xlsx asli via PhpSpreadsheet agar lolos reader maatwebsite.
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'spj').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, 'spj.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Satu baris data valid sesuai urutan kolom template.
     *
     * @param  array<string, string|int|float>  $override
     * @return array<int, string|int|float>
     */
    private function barisValid(array $override = []): array
    {
        return array_values(array_merge([
            'sp2d' => '123/SP2D/2026',
            'sumber' => 'BOS Reguler',
            'no_spk' => '001/SPK/2026',
            'ba_no' => 'BA-01/2026',
            'tgl' => '2026-05-10',
            'kode' => '5.2.02.01.004',
            'merk' => 'Lenovo ThinkPad',
            'sertifikat' => '1234/SERT/2026',
            'ukuran' => '',
            'satuan' => 'UNIT',
            'vol' => 2,
            'harga' => 7500000,
            'jenis' => 'Peralatan & Mesin',
        ], $override));
    }

    private function header(): array
    {
        return [
            'No. SP2D', 'Sumber Perolehan *', 'No. SPK / Kwitansi *',
            'BA NO *', 'BA TGL *', 'Kode Barang', 'Merk / Tipe *',
            'No. Sertifikat', 'Ukuran', 'Satuan *', 'Volume *', 'Harga Satuan *',
            'Jenis *',
        ];
    }

    public function test_import_sukses_multi_item_dan_lookup_katalog(): void
    {
        $sekolah = $this->sekolah('SMKN Import');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(),
            $this->barisValid(['no_spk' => '001/SPK/2026', 'kode' => '5.2.02.02.001', 'satuan' => 'BUAH', 'vol' => 10, 'harga' => 250000, 'jenis' => 'Buku']),
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('pelaporan_bm_spj', 2);
        $this->assertDatabaseHas('pelaporan_bm_spj', [
            'sekolah_id' => $sekolah->id,
            'no_spk' => '001/SPK/2026',
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'no_sp2d' => '123/SP2D/2026',
            'ba_no' => 'BA-01/2026',
            'ba_tgl' => '2026-05-10',
            'merk_tipe' => 'Lenovo ThinkPad',
            'satuan' => 'UNIT',
            'volume' => 2,
            'harga_satuan' => 7500000,
            'nilai_perolehan' => 15000000,
            'bulan_realisasi' => 5,
            'kategori' => 'Peralatan & Mesin',
        ]);
        $this->assertDatabaseHas('pelaporan_bm_spj', [
            'kode_barang' => '5.2.02.02.001',
            'nama_barang' => 'Kursi',
            'satuan' => 'BUAH',
            'nilai_perolehan' => 2500000,
            'kategori' => 'Buku',
        ]);
    }

    public function test_import_ba_tgl_serial_excel(): void
    {
        $sekolah = $this->sekolah('SMKN Serial');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        // 46152 = 2026-05-10 (serial Excel)
        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(['tgl' => 46152]),
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pelaporan_bm_spj', [
            'ba_tgl' => '2026-05-10',
            'satuan' => 'UNIT',
        ]);
    }

    public function test_import_gagal_total_saat_ada_baris_invalid(): void
    {
        $sekolah = $this->sekolah('SMKN Gagal');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(),
            $this->barisValid(['no_spk' => '002/SPK/2026', 'sumber' => '']), // sumber kosong
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        // All-or-nothing: baris valid pun tidak tersimpan.
        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_gagal_kode_barang_tidak_ada_di_katalog(): void
    {
        $sekolah = $this->sekolah('SMKN KodeAsing');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(['kode' => '9.9.99.99.999']),
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_gagal_ba_tgl_invalid(): void
    {
        $sekolah = $this->sekolah('SMKN TglRusak');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(['tgl' => 'tanggal-ngawur']),
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_menolak_jenis_yang_tidak_dikenal(): void
    {
        $sekolah = $this->sekolah('SMKN Jenis Invalid');
        $operator = $this->operator($sekolah, 'op_jenis_invalid');
        $this->buatKatalog();

        $file = $this->xlsx([
            $this->header(),
            $this->barisValid(['jenis' => 'Kendaraan']),
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_ditolak_saat_laporan_terkunci(): void
    {
        $sekolah = $this->sekolah('SMKN Kunci');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();
        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
        ]));

        $file = $this->xlsx([$this->header(), $this->barisValid()]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_operator_selalu_masuk_sekolahnya_sendiri(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operator = $this->operator($sekolahA);
        $this->buatKatalog();

        $file = $this->xlsx([$this->header(), $this->barisValid()]);

        // resolveSekolahId mengabaikan sekolah_id kiriman request.
        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), [
                'file' => $file,
                'bulan' => 5,
                'sekolah_id' => $sekolahB->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('pelaporan_bm_spj', 1);
        $this->assertDatabaseHas('pelaporan_bm_spj', ['sekolah_id' => $sekolahA->id]);
    }

    public function test_import_menolak_berkas_lebih_dari_batas_baris(): void
    {
        $sekolah = $this->sekolah('SMKN Besar');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $rows = [$this->header()];
        for ($i = 0; $i < 5001; $i++) {
            $rows[] = $this->barisValid(['no_spk' => "SPK-{$i}"]);
        }

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $this->xlsx($rows), 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_validasi_payload_request(): void
    {
        $sekolah = $this->sekolah('SMKN Validasi');
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['bulan' => 13])
            ->assertSessionHasErrors(['file', 'bulan']);
    }

    public function test_import_ditolak_untuk_npsn_di_luar_daftar(): void
    {
        // NPSN 20246369/20246370/20252161/20254692 diizinkan; lainnya ditolak.
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN Lain',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20999999',
        ]));
        $operator = $this->operator($sekolah, 'op_npsn_lain');
        $this->buatKatalog();

        $file = $this->xlsx([$this->header(), $this->barisValid()]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('error', 'Fitur import Excel belum tersedia untuk sekolah Anda.');

        $this->assertDatabaseCount('pelaporan_bm_spj', 0);
    }

    public function test_import_diperbolehkan_untuk_npsn_dalam_daftar(): void
    {
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN 4 KUNINGAN',
            'kota_kab' => 'Kab. Kuningan',
            'npsn' => '20246369',
        ]));
        $operator = $this->operator($sekolah, 'op_npsn_daftar');
        $this->buatKatalog();

        $file = $this->xlsx([$this->header(), $this->barisValid()]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('pelaporan_bm_spj', 1);
    }

    public function test_index_mengirim_flag_can_import_spj_sesuai_npsn(): void
    {
        $sekolahDiizinkan = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN 1 LURAGUNG',
            'kota_kab' => 'Kab. Kuningan',
            'npsn' => '20254692',
        ]));
        $sekolahDitolak = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN Lain',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20999998',
        ]));

        $userDiizinkan = $this->operator($sekolahDiizinkan, 'op_lrg');
        $userDitolak = $this->operator($sekolahDitolak, 'op_lain');

        $this->actingAs($userDiizinkan)
            ->get(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canImportSpj', true));

        $this->actingAs($userDitolak)
            ->get(route('pelaporan-bm.spj.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canImportSpj', false));
    }

    public function test_import_tercatat_di_log_dan_disembunyikan_dari_admin_kcd(): void
    {
        $sekolah = $this->sekolah('SMKN Log');
        $operator = $this->operator($sekolah);
        $this->buatKatalog();

        $file = $this->xlsx([$this->header(), $this->barisValid()]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.spj.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Log tercatat dengan flag penyembunyian.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'import-spj',
            'description' => 'import-spj',
        ]);

        // super_admin melihat log import; admin_kcd tidak (login/logout test ikut tercatat,
        // jadi asersi berbasis keberadaan event import-spj, bukan total baris).
        $superAdmin = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Admin',
            'username' => 'sa_import',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $superAdmin->assignRole('super_admin');

        $adminKcd = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => 'ak_import',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $adminKcd->assignRole('admin_kcd');

        $this->actingAs($superAdmin)
            ->get(route('admin.log-aktivitas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data.0.event', 'import-spj'));

        $this->actingAs($adminKcd)
            ->get(route('admin.log-aktivitas.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data', fn ($rows) => collect($rows)->every(
                    fn ($row) => ($row['event'] ?? '') !== 'import-spj'
                )));
    }
}
