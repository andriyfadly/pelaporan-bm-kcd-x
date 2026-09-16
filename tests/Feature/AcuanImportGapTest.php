<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class AcuanImportGapTest extends TestCase
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

    private function operator(Sekolah $sekolah, string $username = 'op_acuan'): User
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

    private function adminTanpaSekolah(string $username = 'admin_acuan'): User
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

    /**
     * Bangun file .xlsx minimal dari baris (array kolom) tanpa shared strings.
     *
     * @param  array<int, array<int, string|int|float>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension missing');
        }

        $cells = '';
        foreach ($rows as $r => $row) {
            $excelRow = $r + 1;
            $cells .= "<row r=\"{$excelRow}\">";
            foreach ($row as $c => $val) {
                $col = chr(65 + $c);
                $cells .= "<c r=\"{$col}{$excelRow}\"><v>".htmlspecialchars((string) $val, ENT_XML1).'</v></c>';
            }
            $cells .= '</row>';
        }

        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<sheetData>{$cells}</sheetData></worksheet>";

        $path = tempnam(sys_get_temp_dir(), 'acuan').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return new UploadedFile($path, 'acuan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_melewati_baris_pendek_dan_uraian_kosong_lalu_menghitung_skipped(): void
    {
        $sekolah = $this->sekolah('SMKN Skip');
        $operator = $this->operator($sekolah);

        $file = $this->xlsx([
            ['Satuan', 'NPSN', 'Tanggal', 'Kodering', 'BKU', 'Uraian', 'Nominal', 'Bulan'],
            ['S', 'N', '2026-05-10'],                      // pendek -> skipped
            ['S', 'N', '2026-05-10', '5.2.02.01', 'B1', '', '1000', '5'],   // uraian kosong -> skipped
            ['S', 'N', 'tgl-ngawur', '5.2.02.02', 'B2', 'Uraian', '2000', '5'], // tanggal invalid -> skipped
            ['S', 'N', '2026-05-11', '5.2.02.03', 'B3', 'Valid', '3.000', '5'], // valid, nominal "3.000"
        ]);

        $response = $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect();

        $pesan = $response->getSession()->get('success');
        $this->assertStringContainsString('1 data acuan', $pesan);
        $this->assertStringContainsString('3 baris dilewati', $pesan);

        $this->assertDatabaseCount('pelaporan_bm_acuan', 1);
        $this->assertDatabaseHas('pelaporan_bm_acuan', [
            'kodering' => '5.2.02.03',
            'uraian' => 'Valid',
            'nominal' => 3000,
            'bulan' => 5,
            'sekolah_id' => $sekolah->id,
        ]);
    }

    public function test_import_menangani_tanggal_serial_excel(): void
    {
        $sekolah = $this->sekolah('SMKN Serial');
        $operator = $this->operator($sekolah);

        // 46152 = 2026-05-10 (serial Excel, >25569 agar masuk cabang gmdate)
        $file = $this->xlsx([
            ['Satuan', 'NPSN', 'Tanggal', 'Kodering', 'BKU', 'Uraian', 'Nominal', 'Bulan'],
            ['S', 'N', 46152, '5.2.02.10', 'B1', 'Serial', '5000', '5'],
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect();

        $acuan = Acuan::where('kodering', '5.2.02.10')->first();
        $this->assertNotNull($acuan);
        $this->assertEquals('2026-05-10', (string) $acuan->tanggal);
    }

    public function test_import_memakai_bulan_dari_request_saat_kolom_bulan_kosong(): void
    {
        $sekolah = $this->sekolah('SMKN BulanReq');
        $operator = $this->operator($sekolah);

        $file = $this->xlsx([
            ['Satuan', 'NPSN', 'Tanggal', 'Kodering', 'BKU', 'Uraian', 'Nominal', 'Bulan'],
            ['S', 'N', '2026-07-01', '5.2.02.20', 'B1', 'Pakai Bulan Request', '7000', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $file, 'bulan' => 7])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_acuan', ['kodering' => '5.2.02.20', 'bulan' => 7]);
    }

    public function test_import_admin_menargetkan_sekolah_via_npsn(): void
    {
        $admin = $this->adminTanpaSekolah();
        $target = $this->sekolah('SMKN NPSN', '12345678');

        $file = $this->xlsx([
            ['Satuan', 'NPSN', 'Tanggal', 'Kodering', 'BKU', 'Uraian', 'Nominal', 'Bulan'],
            ['SMKN NPSN', '12345678', '2026-05-10', '5.2.02.30', 'B1', 'Via NPSN', '8000', '5'],
        ]);

        $this->actingAs($admin)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $file, 'bulan' => 5])
            ->assertRedirect();

        $this->assertDatabaseHas('pelaporan_bm_acuan', [
            'kodering' => '5.2.02.30',
            'sekolah_id' => $target->id,
        ]);
    }

    public function test_import_menolak_berkas_lebih_dari_batas_baris(): void
    {
        $sekolah = $this->sekolah('SMKN Besar');
        $operator = $this->operator($sekolah);

        $rows = [['Satuan', 'NPSN', 'Tanggal', 'Kodering', 'BKU', 'Uraian', 'Nominal', 'Bulan']];
        for ($i = 0; $i < 5001; $i++) {
            $rows[] = ['S', 'N', '2026-05-10', '5.2.02.40', 'B1', 'Banyak', '1', '5'];
        }

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.import'), ['file' => $this->xlsx($rows), 'bulan' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_acuan', 0);
    }

    public function test_store_menolak_payload_tidak_lengkap(): void
    {
        $sekolah = $this->sekolah('SMKN Store');
        $operator = $this->operator($sekolah);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.store'), [
                'tanggal' => '2026-05-10',
                'uraian' => '',
                'nominal' => -5,
                'bulan' => 13,
            ])
            ->assertSessionHasErrors(['uraian', 'nominal', 'bulan']);

        $this->assertDatabaseCount('pelaporan_bm_acuan', 0);
    }

    public function test_store_memaksa_sekolah_id_operator_meski_dikirim_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operator = $this->operator($sekolahA);

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.acuan.store'), [
                'sekolah_id' => $sekolahB->id,
                'tanggal' => '2026-05-10',
                'kodering' => '5.2.02.01',
                'uraian' => 'Belanja',
                'nominal' => 1000,
                'bulan' => 5,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pelaporan_bm_acuan', ['uraian' => 'Belanja', 'sekolah_id' => $sekolahA->id]);
    }

    public function test_destroy_menolak_acuan_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operator = $this->operator($sekolahA);

        $acuanLain = activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolahB->id,
            'tanggal' => '2026-05-10',
            'kodering' => '5.2.02.01',
            'uraian' => 'Milik B',
            'nominal' => 1000,
            'bulan' => 5,
        ]));

        $this->actingAs($operator)
            ->delete(route('pelaporan-bm.acuan.destroy', $acuanLain->id))
            ->assertForbidden();

        $this->assertDatabaseHas('pelaporan_bm_acuan', ['id' => $acuanLain->id]);
    }

    public function test_user_sekolah_tanpa_sekolah_id_ditolak_403(): void
    {
        // Operator tanpa sekolah_id: resolveSekolahId harus abort 403 (bukan bocor lintas tenant).
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Tanpa Sekolah',
            'username' => 'op_tanpa_sekolah',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('pelaporan-bm.rekapan.index'))
            ->assertForbidden();
    }
}
