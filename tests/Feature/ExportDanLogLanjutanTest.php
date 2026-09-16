<?php

namespace Tests\Feature;

use App\Exports\CetakBmExport;
use App\Exports\RealisasiBmExport;
use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use ZipArchive;

class ExportDanLogLanjutanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN 1 Export'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_export'): User
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

    private function admin(string $username = 'admin_export'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('Password123!'),
        ]));
        $user->assignRole('admin_kcd');

        return $user;
    }

    private function realisasi(Sekolah $sekolah, array $over = []): Realisasi
    {
        return activity()->withoutLogging(fn () => Realisasi::create(array_merge([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.05.01.0001',
            'bulan_realisasi' => 5,
            'no_spk' => 'SPK/EXP/01',
            'no_sp2d' => 'SP2D-01',
            'sumber_perolehan' => 'BOS Reguler',
            'ba_no' => 'BA/01',
            'ba_tgl' => '2026-05-10',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Export',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 2,
            'harga_satuan' => 10000000,
            'nilai_perolehan' => 20000000,
        ], $over)));
    }

    private function loadSheetBinary(string $binary, string $sheet = 'Laporan Belanja Modal'): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($path, $binary);
        try {
            $book = IOFactory::load($path);

            return $book->getSheetByName($sheet) ?? $book->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    public function test_export_cetak_isi_cell_dan_cookie_dan_log(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '1.3.2.05',
            'uraian' => 'Laptop',
            'kodering_aset' => '1.3.2',
            'jenis_aset' => 'Peralatan dan Mesin',
            'umur_ekonomis' => 4,
        ]));
        $this->realisasi($sekolah);
        Activity::query()->delete();

        $response = $this->actingAs($operator)
            ->get(route('pelaporan-bm.cetak.unduh', ['bulan' => 5, 'tahun' => 2026]));
        $response->assertOk();
        $response->assertCookie('download_status', 'complete');
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'unduh-cetak',
        ]);

        // Baca-balik isi: header A8, data A10, VLOOKUP dinamis, SUM.
        $items = Realisasi::with(['sekolah', 'acuan'])->get();
        $binary = Excel::raw(new CetakBmExport($items, $sekolah->nama_sekolah, 5, 2026), ExcelWriter::XLSX);
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals('No', $sheet->getCell('A8')->getValue());
        $this->assertEquals(1, $sheet->getCell('A10')->getValue());
        $this->assertEquals('SPK/EXP/01', $sheet->getCell('E10')->getValue());
        $this->assertStringContainsString('VLOOKUP(J10', (string) $sheet->getCell('K10')->getValue());
        $this->assertEquals('=P10*Q10', (string) $sheet->getCell('R10')->getValue());
        $this->assertEquals('=SUM(R10:R10)', (string) $sheet->getCell('R7')->getValue());
    }

    public function test_export_realisasi_isi_dan_cookie_dan_log(): void
    {
        $sekolah = $this->sekolah('SMKN 2 Export');
        $operator = $this->operator($sekolah, 'op_export2');
        $this->realisasi($sekolah, ['bulan_realisasi' => 7, 'ba_tgl' => '2026-07-15']);
        Activity::query()->delete();

        $response = $this->actingAs($operator)
            ->get(route('pelaporan-bm.realisasi.unduh', ['filter_bulan' => 7, 'filter_tahun' => 2026]));
        $response->assertOk();
        $response->assertCookie('download_status', 'complete');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sistem',
            'event' => 'unduh-realisasi',
        ]);

        $items = Realisasi::with('sekolah')->get();
        $binary = Excel::raw(new RealisasiBmExport($items, $sekolah->nama_sekolah, 2026), ExcelWriter::XLSX);
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals('No', $sheet->getCell('A8')->getValue());
        $this->assertEquals(1, $sheet->getCell('A10')->getValue());
        $this->assertStringContainsString('VLOOKUP(J10', (string) $sheet->getCell('K10')->getValue());
        $this->assertEquals('=SUM(R10:R10)', (string) $sheet->getCell('R7')->getValue());
    }

    public function test_export_kosong_204_cookie_empty_tanpa_log(): void
    {
        $sekolah = $this->sekolah('SMKN Kosong');
        $operator = $this->operator($sekolah, 'op_kosong');
        Activity::query()->delete();

        $this->actingAs($operator)->get(route('pelaporan-bm.unduh', ['bulan' => 5]))
            ->assertNoContent(204)->assertCookie('download_status', 'empty');
        $this->actingAs($operator)->get(route('pelaporan-bm.realisasi.unduh'))
            ->assertNoContent(204)->assertCookie('download_status', 'empty');
        $this->actingAs($operator)->get(route('pelaporan-bm.cetak.unduh', ['bulan' => 5, 'tahun' => 2026]))
            ->assertNoContent(204)->assertCookie('download_status', 'empty');

        $this->assertDatabaseMissing('activity_log', ['event' => 'unduh-spj']);
        $this->assertDatabaseMissing('activity_log', ['event' => 'unduh-realisasi']);
        $this->assertDatabaseMissing('activity_log', ['event' => 'unduh-cetak']);
    }

    public function test_kirim_laporan_sukses_dan_log(): void
    {
        $sekolah = $this->sekolah('SMKN Kirim');
        $operator = $this->operator($sekolah, 'op_kirim');
        activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 20000000,
            'bulan' => 8,
            'uraian' => 'Pengadaan Laptop',
        ]));
        $this->realisasi($sekolah, ['bulan_realisasi' => 8, 'ba_tgl' => '2026-08-10']);
        Activity::query()->delete();

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.input-realisasi.kirim-laporan'), ['bulan_realisasi' => 8])
            ->assertRedirect()
            ->assertSessionHas('success');

        $kunci = KunciLaporan::where('sekolah_id', $sekolah->id)->where('bulan', '8')->first();
        $this->assertNotNull($kunci);
        $this->assertEquals('menunggu_approval', $kunci->status_kirim);
        $this->assertTrue((bool) $kunci->status_kunci);
        $this->assertDatabaseHas('activity_log', ['event' => 'kirim-laporan']);
    }

    public function test_verifikasi_dan_buka_kunci_tercatat(): void
    {
        $sekolah = $this->sekolah('SMKN Kunci');
        $admin = $this->admin();
        Activity::query()->delete();

        // Kunci
        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.toggle'), [
            'sekolah_id' => $sekolah->id, 'bulan' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'kunci-laporan']);

        // Buka kunci
        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.toggle'), [
            'sekolah_id' => $sekolah->id, 'bulan' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'buka-kunci-laporan']);

        // Verifikasi → disetujui
        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.status'), [
            'sekolah_id' => $sekolah->id, 'bulan' => 5, 'status_kirim' => 'disetujui',
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'verifikasi-laporan']);
    }

    public function test_logout_dan_ganti_password_tercatat(): void
    {
        $sekolah = $this->sekolah('SMKN Logout');
        $operator = $this->operator($sekolah, 'op_logout');
        Activity::query()->delete();

        $this->post('/login', ['username' => 'op_logout', 'password' => 'Password123!'])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'login']);

        $this->actingAs($operator)->post('/logout')->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'logout']);

        Activity::query()->delete();
        $this->actingAs($operator)->post(route('password.update'), [
            'current_password' => 'Password123!',
            'password' => 'BaruKuat#123',
            'password_confirmation' => 'BaruKuat#123',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('activity_log', ['event' => 'ganti-password']);
    }

    public function test_manajemen_user_log_tercatat(): void
    {
        $admin = $this->admin('admin_userlog');
        $sekolah = $this->sekolah('SMKN Userlog');
        Activity::query()->delete();

        $this->actingAs($admin)->post(route('admin.user.store'), [
            'name' => 'Operator Log',
            'username' => 'op_log1',
            'password' => 'Secret123!',
            'role' => 'operator_sekolah',
            'sekolah_id' => $sekolah->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'tambah-user']);

        $user = User::where('username', 'op_log1')->first();
        $this->assertNotNull($user);

        $this->actingAs($admin)->put(route('admin.user.update', $user), [
            'name' => 'Operator Log Edit',
            'username' => 'op_log1',
            'role' => 'operator_sekolah',
            'sekolah_id' => $sekolah->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'ubah-user']);

        $this->actingAs($admin)->delete(route('admin.user.destroy', $user))->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'hapus-user']);
    }

    public function test_import_acuan_dan_hapus_massal_tercatat(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }
        $sekolah = $this->sekolah('SMKN Impor');
        $operator = $this->operator($sekolah, 'op_impor');

        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="9" uniqueCount="9"><si><t>Satuan</t></si><si><t>NPSN</t></si><si><t>Tanggal</t></si><si><t>Kodering</t></si><si><t>BKU</t></si><si><t>Uraian</t></si><si><t>Nominal</t></si><si><t>Bulan</t></si><si><t>Komputer Impor</t></si></sst>';
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c><c r="H1" t="s"><v>7</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>0</v></c><c r="B2"><v>0</v></c><c r="C2"><v>2026-05-10</v></c><c r="D2"><v>5.2.02.05</v></c><c r="E2"><v>BKU-01</v></c><c r="F2" t="s"><v>8</v></c><c r="G2"><v>25000000</v></c><c r="H2"><v>5</v></c></row>'
            .'</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'acuan').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $file = new UploadedFile($path, 'acuan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        Activity::query()->delete();
        $this->actingAs($operator)->post(route('pelaporan-bm.acuan.import'), [
            'file' => $file, 'bulan' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'import-acuan']);
        @unlink($path);

        $this->actingAs($operator)->post(route('pelaporan-bm.acuan.destroy-all'), ['bulan' => 5])->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'hapus-massal-acuan']);
    }

    public function test_hapus_spk_sinkron_dan_hapus_item_tercatat(): void
    {
        $sekolah = $this->sekolah('SMKN SPK Log');
        $operator = $this->operator($sekolah, 'op_spklog');
        activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'kodering' => '5.2.02.05.01.0001',
            'nominal' => 50000000,
            'bulan' => 9,
            'uraian' => 'Pengadaan PC',
        ]));

        // Buat SPK 2 item
        $this->actingAs($operator)->post(route('pelaporan-bm.spj.store-spk'), [
            'no_spk' => 'SPK/LOG/01',
            'sumber_perolehan' => 'BOS Reguler',
            'bulan_realisasi' => 9,
            'kategori' => 'Peralatan & Mesin',
            'items' => [
                ['kode_barang' => '1.3.2.05', 'nama_barang' => 'PC A', 'jenis_aset' => 'Peralatan dan Mesin', 'volume' => 1, 'harga_satuan' => 10000000],
                ['kode_barang' => '1.3.2.05', 'nama_barang' => 'PC B', 'jenis_aset' => 'Peralatan dan Mesin', 'volume' => 1, 'harga_satuan' => 10000000],
            ],
        ])->assertRedirect();

        $spjIds = Spj::where('sekolah_id', $sekolah->id)->pluck('id')->all();
        $this->actingAs($operator)->post(route('pelaporan-bm.input-realisasi.simpan'), [
            'kodering' => '5.2.02.05.01.0001',
            'bulan_realisasi' => 9,
            'item_ids' => $spjIds,
        ])->assertRedirect();

        Activity::query()->delete();

        // Edit: ubah 1 item (picu sinkron) + buang 1 item (picu hapus-item-spk)
        $keep = Spj::where('sekolah_id', $sekolah->id)->where('nama_barang', 'PC A')->first();
        $this->actingAs($operator)->post(route('pelaporan-bm.spj.store-spk'), [
            'is_edit' => true,
            'no_spk_lama' => 'SPK/LOG/01',
            'no_spk' => 'SPK/LOG/01',
            'sumber_perolehan' => 'BOS Reguler',
            'bulan_realisasi' => 9,
            'kategori' => 'Peralatan & Mesin',
            'items' => [
                ['id' => $keep->id, 'kode_barang' => '1.3.2.05', 'nama_barang' => 'PC A Updated', 'jenis_aset' => 'Peralatan dan Mesin', 'volume' => 1, 'harga_satuan' => 11000000],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_log', ['event' => 'sinkron-realisasi-spk']);
        $this->assertDatabaseHas('activity_log', ['event' => 'hapus-item-spk']);

        // Hapus dokumen SPK
        $this->actingAs($operator)->delete(route('pelaporan-bm.spj.destroy-spk', [
            'no_spk' => 'SPK/LOG/01', 'bulan' => 9,
        ]))->assertRedirect();
        $this->assertDatabaseHas('activity_log', ['event' => 'hapus-spk']);
    }

    public function test_auto_log_acuan_dan_kode_barang(): void
    {
        $sekolah = $this->sekolah('SMKN Autolog');
        $operator = $this->operator($sekolah, 'op_autolog');
        $admin = $this->admin('admin_autolog');
        Activity::query()->delete();

        // Acuan create via HTTP → auto-log created
        $this->actingAs($operator)->post(route('pelaporan-bm.acuan.store'), [
            'tanggal' => '2026-06-01',
            'kodering' => '5.2.02.05',
            'uraian' => 'Acuan Autolog',
            'nominal' => 5000000,
            'bulan' => 6,
        ])->assertRedirect();

        $acuan = Acuan::where('uraian', 'Acuan Autolog')->first();
        $this->assertNotNull($acuan);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'created',
            'subject_type' => Acuan::class,
            'subject_id' => $acuan->id,
        ]);

        // Kode barang create via HTTP → auto-log created
        $this->actingAs($admin)->post(route('admin.kode-barang.store'), [
            'kode_barang' => '1.3.2.99',
            'uraian' => 'Barang Autolog',
        ])->assertRedirect();

        $kb = KodeBarang::where('kode_barang', '1.3.2.99')->first();
        $this->assertNotNull($kb);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'created',
            'subject_type' => KodeBarang::class,
            'subject_id' => $kb->id,
        ]);
    }

    public function test_unduh_spj_cookie_dan_log(): void
    {
        $sekolah = $this->sekolah('SMKN Unduh SPJ');
        $operator = $this->operator($sekolah, 'op_unduhspj');
        activity()->withoutLogging(fn () => Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK/UND/01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Unduh',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 15000000,
            'nilai_perolehan' => 15000000,
        ]));
        Activity::query()->delete();

        $response = $this->actingAs($operator)->get(route('pelaporan-bm.unduh', ['bulan' => 5]));
        $response->assertOk();
        $response->assertCookie('download_status', 'complete');
        $this->assertDatabaseHas('activity_log', ['event' => 'unduh-spj']);
    }
}
