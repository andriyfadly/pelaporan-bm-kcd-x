<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BhpBarangImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => 'SMKN Barang', 'kota_kab' => 'Kota Bandung']);
    }

    private function operator(): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.barang',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah()->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function rekening(string $kode = '5.1.02.01.01.0001', ?string $kodeBarang = '1.1.7.01.01', string $sumberDana = 'BOS'): KodeRekening
    {
        return KodeRekening::create([
            'kode' => $kode,
            'nama' => 'Rekening Bahan',
            'kode_barang' => $kodeBarang,
            'uraian_kode_barang' => $kodeBarang ? 'Bahan' : null,
            'sumber_dana' => $sumberDana,
        ]);
    }

    /** @param array<int, array<int, string|int|float|null>> $rows */
    private function xlsx(array $rows, array $teksCells = []): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $nilai) {
                if ($nilai === null) {
                    continue;
                }

                $koordinat = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
                if (in_array($colIndex, $teksCells[$rowIndex] ?? [], true)) {
                    $sheet->setCellValueExplicit($koordinat, (string) $nilai, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($koordinat, $nilai);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'barang-import').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, 'barang.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private const HEADER = ['Kode Rekening*', 'Nama Barang*', 'Spesifikasi/NUSP', 'Satuan*', 'Harga Satuan*'];

    public function test_import_barang_berhasil_membuat_barang_dan_harga(): void
    {
        $this->rekening();
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0001', 'Kertas HVS', 'A4 70gsm', 'Rim', 50000],
        ]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertRedirect();

        $barang = Barang::firstOrFail();
        $this->assertSame('Kertas HVS', $barang->nama_barang);
        $this->assertSame('1.1.7.01.01', $barang->kode_barang);
        $this->assertSame('A4 70gsm', $barang->spesifikasi_barang_nusp);
        $this->assertSame(1, HargaBarang::where('barang_id', $barang->id)->count());
        $this->assertSame(50000, (int) HargaBarang::first()->harga_satuan);
    }

    public function test_import_nama_sama_menambah_varian_harga(): void
    {
        $this->rekening();
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0001', 'Kertas HVS', '', 'Rim', 50000],
            ['5.1.02.01.01.0001', 'Kertas HVS', '', 'Rim', 55000],
        ]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertRedirect();

        $this->assertSame(1, Barang::count());
        $this->assertSame(2, HargaBarang::count());
    }

    public function test_import_batal_semua_saat_ada_baris_invalid(): void
    {
        $this->rekening();
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0001', 'Kertas HVS', '', 'Rim', 50000],
            ['5.1.02.01.01.9999', 'Pulpen', '', 'Pcs', 5000], // rekening tidak ada
            ['5.1.02.01.01.0001', '', '', 'Pcs', 5000], // nama kosong
        ]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertSessionHas('error');

        $this->assertSame(0, Barang::count());
        $this->assertSame(0, HargaBarang::count());
    }

    public function test_import_menolak_harga_nol_atau_negatif(): void
    {
        $this->rekening();
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0001', 'Kertas HVS', '', 'Rim', 0],
        ]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertSessionHas('error');

        $this->assertSame(0, Barang::count());
    }

    public function test_import_menolak_berkas_tanpa_baris_data(): void
    {
        $file = $this->xlsx([self::HEADER]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertSessionHas('error');

        $this->assertSame(0, Barang::count());
    }

    public function test_import_menerima_harga_berformat_ribuan(): void
    {
        $this->rekening();
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0001', 'Kertas HVS', '', 'Rim', '1.500.000'],
        ], teksCells: [1 => [4]]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertRedirect();

        $this->assertSame(1500000, (int) HargaBarang::first()->harga_satuan);
    }

    public function test_import_mengikuti_sumber_dana_bopd(): void
    {
        $this->rekening('5.1.02.01.01.0002', '1.1.7.01.01', 'BOPD');
        $file = $this->xlsx([
            self::HEADER,
            ['5.1.02.01.01.0002', 'Semen', '', 'Sak', 60000],
        ]);

        $this->actingAs($this->operator())
            ->post(route('distribusi-bhp.bopd.barang.import'), ['file' => $file, 'sumber_dana' => 'BOPD'])
            ->assertRedirect();

        $barang = Barang::firstOrFail();
        $this->assertSame('BOPD', $barang->sumber_dana);
    }

    public function test_template_dapat_diunduh(): void
    {
        $this->rekening();

        $this->actingAs($this->operator())
            ->get(route('distribusi-bhp.bos.barang.template', ['sumber_dana' => 'BOS']))
            ->assertOk();
    }

    public function test_bendahara_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.barang',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $file = $this->xlsx([self::HEADER]);

        $this->actingAs($bendahara)
            ->post(route('distribusi-bhp.bos.barang.import'), ['file' => $file, 'sumber_dana' => 'BOS'])
            ->assertForbidden();
    }
}
