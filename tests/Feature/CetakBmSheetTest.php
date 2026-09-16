<?php

namespace Tests\Feature;

use App\Exports\CetakBmExport;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class CetakBmSheetTest extends TestCase
{
    use RefreshDatabase;

    private function loadSheetBinary(string $binary): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /**
     * Bangun satu baris Realisasi + sekolah untuk pengujian helper export.
     */
    private function baris(array $override = []): Realisasi
    {
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $override['nama_sekolah'] ?? 'SMKN Uji',
            'kota_kab' => $override['kota_kab'] ?? 'Kota Bandung',
        ]));

        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => $override['kodering_belanja'] ?? '5.2.02.01',
            'bulan_realisasi' => 5,
            'ba_tgl' => '2026-05-10',
            'no_spk' => $override['no_spk'] ?? 'SPK/01',
            'kode_barang' => $override['kode_barang'] ?? '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));
    }

    public function test_safe_cell_menetralkan_formula_injection(): void
    {
        // Nilai berbahaya harus diawali apostrof agar tak dieksekusi sebagai formula.
        $row = $this->baris([
            'no_spk' => '=CMD|calc',
            'kodering_belanja' => '+SUM(1,1)',
            'kode_barang' => '1.3.2.09',
        ]);

        $binary = Excel::raw(
            new CetakBmExport(Realisasi::with(['sekolah', 'acuan'])->get(), 'SMKN Uji', 5, 2026),
            ExcelWriter::XLSX
        );
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals("'=CMD|calc", $sheet->getCell('E10')->getValue());
        $this->assertEquals("'+SUM(1,1)", $sheet->getCell('D10')->getValue());
        $this->assertStringNotContainsString('Kota Bandung', (string) $row->no_spk);
    }

    public function test_kota_kab_dinormalkan_ke_prefix_kabupaten(): void
    {
        $this->baris(['kota_kab' => 'Kab. Bandung Barat', 'kode_barang' => '1.3.2.10']);

        $binary = Excel::raw(
            new CetakBmExport(Realisasi::with(['sekolah', 'acuan'])->get(), 'SMKN Uji', 5, 2026),
            ExcelWriter::XLSX
        );
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals('KABUPATEN BANDUNG BARAT', $sheet->getCell('Z10')->getValue());
    }

    public function test_kota_kab_kota_dipertahankan(): void
    {
        $this->baris(['kota_kab' => 'Kota Cimahi', 'kode_barang' => '1.3.2.11']);

        $binary = Excel::raw(
            new CetakBmExport(Realisasi::with(['sekolah', 'acuan'])->get(), 'SMKN Uji', 5, 2026),
            ExcelWriter::XLSX
        );
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals('KOTA CIMAHI', $sheet->getCell('Z10')->getValue());
    }

    public function test_kota_kab_tanpa_prefix_diberi_kabupaten(): void
    {
        $this->baris(['kota_kab' => 'Sumedang', 'kode_barang' => '1.3.2.12']);

        $binary = Excel::raw(
            new CetakBmExport(Realisasi::with(['sekolah', 'acuan'])->get(), 'SMKN Uji', 5, 2026),
            ExcelWriter::XLSX
        );
        $sheet = $this->loadSheetBinary($binary);

        $this->assertEquals('KABUPATEN SUMEDANG', $sheet->getCell('Z10')->getValue());
    }
}
