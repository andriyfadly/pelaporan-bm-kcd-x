<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class KodeBarangImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin_imp', 'password' => bcrypt('password')]);
        $admin->assignRole('admin_kcd');

        return $admin;
    }

    public function test_import_csv_semicolon_delimiter(): void
    {
        $csv = "Kode;Uraian;Kodering;Jenis;Umur;Satuan;Harga\n1.3.2.01.01;Meja;5.2.02.01;Peralatan dan Mesin;5;Unit;1000000\n";
        $file = UploadedFile::fake()->createWithContent('kb.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('admin.kode-barang.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('master_data_kode_barang', ['kode_barang' => '1.3.2.01.01']);
    }

    public function test_import_skips_empty_kode_and_applies_defaults(): void
    {
        // Baris tanpa kolom tambahan: uraian/satuan/jenis memakai default
        $csv = "Kode,Uraian,Kodering,Jenis,Umur,Satuan,Harga\n,Baris Kosong,,,,,,\n1.3.2.01.02\n";
        $file = UploadedFile::fake()->createWithContent('kb2.csv', $csv);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.kode-barang.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertStringContainsString('1 data', $response->getSession()->get('success'));

        $kb = KodeBarang::where('kode_barang', '1.3.2.01.02')->first();
        $this->assertNotNull($kb);
        $this->assertEquals('Tanpa Nama', $kb->uraian);
        $this->assertEquals('Unit', $kb->satuan);
        $this->assertEquals('Peralatan dan Mesin', $kb->jenis_aset);
    }

    public function test_import_is_idempotent_update_or_create(): void
    {
        $make = fn (string $uraian) => UploadedFile::fake()->createWithContent(
            'kb.csv',
            "Kode,Uraian\n1.3.2.01.03,{$uraian}\n"
        );
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.kode-barang.import'), ['file' => $make('Awal')])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.kode-barang.import'), ['file' => $make('Diubah')])->assertRedirect();

        $this->assertEquals(1, KodeBarang::where('kode_barang', '1.3.2.01.03')->count());
        $this->assertEquals('Diubah', KodeBarang::where('kode_barang', '1.3.2.01.03')->first()->uraian);
    }

    public function test_import_xlsx(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }

        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3"><si><t>Kode</t></si><si><t>1.3.2.01.04</t></si><si><t>Kursi</t></si></sst>';
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1"><v>x</v></c></row><row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2" t="s"><v>2</v></c></row></sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'kb').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $file = new UploadedFile($path, 'kb.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->admin())
            ->post(route('admin.kode-barang.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('master_data_kode_barang', ['kode_barang' => '1.3.2.01.04', 'uraian' => 'Kursi']);
        @unlink($path);
    }

    public function test_import_menolak_lebih_dari_20000_baris(): void
    {
        // CSV (bukan xlsx) agar cepat: tetap melewati cabang batas baris.
        $rows = "Kode,Uraian\n";
        for ($i = 0; $i < 20001; $i++) {
            $rows .= "X.{$i},Barang {$i}\n";
        }
        $file = UploadedFile::fake()->createWithContent('kb_besar.csv', $rows);

        $this->actingAs($this->admin())
            ->post(route('admin.kode-barang.import'), ['file' => $file])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('master_data_kode_barang', 0);
    }

    public function test_import_xlsx_rusak_tidak_menyimpan_apa_pun(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }

        // Ekstensi xlsx tapi isi bukan arsip zip -> parseXlsx mengembalikan [].
        $file = UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan zip sama sekali');

        $this->actingAs($this->admin())
            ->post(route('admin.kode-barang.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseCount('master_data_kode_barang', 0);
    }

    public function test_import_xlsx_rich_text_run(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }

        // Shared string berupa rich-text run: <si><r><t>..</t></r>...
        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><r><t>1.3.2.01.05</t></r><r><t></t></r></si>'
            .'<si><r><t>Lap</t></r><r><t>top</t></r></si></sst>';
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1"><v>head</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>0</v></c><c r="B2" t="s"><v>1</v></c></row>'
            .'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'kb').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $file = new UploadedFile($path, 'kb.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        try {
            $this->actingAs($this->admin())
                ->post(route('admin.kode-barang.import'), ['file' => $file])
                ->assertRedirect();

            $this->assertDatabaseHas('master_data_kode_barang', [
                'kode_barang' => '1.3.2.01.05',
                'uraian' => 'Laptop',
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_index_mendukung_json_untuk_autocomplete(): void
    {
        $this->actingAs($this->admin());
        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '1.3.2.09', 'uraian' => 'Autocomplete',
        ]));

        $this->getJson(route('admin.kode-barang.index', ['search' => 'Autocomplete']))
            ->assertOk()
            ->assertJsonFragment(['kode_barang' => '1.3.2.09']);
    }
}
