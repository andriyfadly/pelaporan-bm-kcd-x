<?php

namespace Tests\Feature;

use App\Http\Controllers\PelaporanBm\AcuanController;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class AcuanIndexEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama, ?string $npsn = null): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => $npsn,
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_acuan_edge'): User
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

    private function adminTanpaSekolah(string $username = 'admin_acuan_edge'): User
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

    private function acuan(Sekolah $sekolah, array $over = []): Acuan
    {
        return activity()->withoutLogging(fn () => Acuan::create(array_merge([
            'sekolah_id' => $sekolah->id,
            'tanggal' => '2026-05-10',
            'kodering' => '5.2.02.01',
            'uraian' => 'Belanja',
            'nominal' => 1000,
            'bulan' => 5,
        ], $over)));
    }

    public function test_default_bulan_di_januari_mundur_ke_desember(): void
    {
        // Januari: 1 - 1 = 0 -> dibulatkan mundur ke Desember.
        $controller = new AcuanController;
        $method = new \ReflectionMethod($controller, 'defaultBulan');
        $method->setAccessible(true);

        $this->assertSame(12, $method->invoke($controller, 1));
        $this->assertSame(11, $method->invoke($controller, 12));
        $this->assertSame(4, $method->invoke($controller, 5));
        // Tanpa argumen: selalu dalam rentang 1..12 apa pun bulan berjalan.
        $this->assertContains($method->invoke($controller, null), range(1, 12));
    }

    public function test_search_satuan_mencari_nama_sekolah_dan_npsn(): void
    {
        $admin = $this->adminTanpaSekolah();
        $smkn = $this->sekolah('SMKN 1 Pencarian', '11112222');
        $smpn = $this->sekolah('SMPN 2 Lain');
        $this->acuan($smkn, ['bulan' => 5, 'uraian' => 'Punya SMKN']);
        $this->acuan($smpn, ['bulan' => 5, 'uraian' => 'Punya SMPN']);

        // Cari berdasar nama sekolah.
        $this->actingAs($admin)
            ->get(route('pelaporan-bm.acuan.index', ['bulan' => 5, 'search_satuan' => 'Pencarian']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.uraian', 'Punya SMKN')
            );

        // Cari berdasar NPSN.
        $this->actingAs($admin)
            ->get(route('pelaporan-bm.acuan.index', ['bulan' => 5, 'search_satuan' => '11112222']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_search_satuan_meng_escape_wildcard_like(): void
    {
        // '%' pada input harus di-escape: tidak boleh jadi wildcard yang
        // mencocokkan semua sekolah (potensi kebocoran/bingung filter).
        $admin = $this->adminTanpaSekolah();
        $sekolah = $this->sekolah('SMKN Persen');
        $this->acuan($sekolah, ['bulan' => 5, 'uraian' => 'Persen']);

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.acuan.index', ['bulan' => 5, 'search_satuan' => '%']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 0));
    }

    public function test_parse_tanggal_kosong_menghasilkan_string_kosong(): void
    {
        $controller = new AcuanController;
        $method = new \ReflectionMethod($controller, 'parseTanggal');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($controller, ''));
        $this->assertSame('', $method->invoke($controller, '   '));
    }

    public function test_parse_xlsx_zip_rusak_menghasilkan_array_kosong(): void
    {
        $controller = new AcuanController;
        $method = new \ReflectionMethod($controller, 'parseXlsx');
        $method->setAccessible(true);

        $bukanZip = tempnam(sys_get_temp_dir(), 'bukan').'.xlsx';
        file_put_contents($bukanZip, 'ini bukan file zip sama sekali');

        try {
            $this->assertSame([], $method->invoke($controller, $bukanZip));
        } finally {
            @unlink($bukanZip);
        }
    }

    public function test_import_menangani_rich_text_dan_inline_string(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }

        $sekolah = $this->sekolah('SMKN RichText');
        $operator = $this->operator($sekolah, 'op_richtext');

        // Shared string berisi rich-text run (si/ r/ t) -> teks harus digabung.
        // Inline string untuk uraian.
        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><r><t>Satuan</t></r><r><t></t></r></si>'
            .'<si><t>NPSN</t></si><si><t>Tanggal</t></si><si><t>Kodering</t></si>'
            .'<si><t>BKU</t></si><si><t>Uraian</t></si><si><t>Nominal</t></si><si><t>Bulan</t></si></sst>';
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c><c r="H1" t="s"><v>7</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>0</v></c><c r="B2"><v>0</v></c><c r="C2"><v>2026-05-10</v></c><c r="D2"><v>5.2.02.88</v></c><c r="E2"><v>BKU-R</v></c><c r="F2" t="inlineStr"><is><t>Uraian Inline</t></is></c><c r="G2"><v>9000</v></c><c r="H2"><v>5</v></c></row>'
            .'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'acuan').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $file = new UploadedFile($path, 'acuan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        try {
            $this->actingAs($operator)
                ->post(route('pelaporan-bm.acuan.import'), ['file' => $file, 'bulan' => 5])
                ->assertRedirect();

            $this->assertDatabaseHas('pelaporan_bm_acuan', [
                'kodering' => '5.2.02.88',
                'uraian' => 'Uraian Inline',
                'nominal' => 9000,
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_index_admin_tanpa_sekolah_mengirim_daftar_sekolah(): void
    {
        $admin = $this->adminTanpaSekolah('admin_list');
        $this->sekolah('SMKN Satu');
        $this->sekolah('SMKN Dua');

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.acuan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sekolahs', 2));
    }

    public function test_index_menerima_bulan_kosong_tanpa_filter(): void
    {
        // filterBulan === '' -> tidak menambahkan where bulan.
        $admin = $this->adminTanpaSekolah('admin_kosong');
        $sekolah = $this->sekolah('SMKN Kosong');
        $this->acuan($sekolah, ['bulan' => 3, 'uraian' => 'Maret']);
        $this->acuan($sekolah, ['bulan' => 7, 'uraian' => 'Juli']);

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.acuan.index', ['bulan' => '']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filterBulan', '')
                ->has('items.data', 2)
            );
    }
}
