<?php

namespace Tests\Feature;

use App\Exports\CetakBmSheet;
use App\Exports\RealisasiBmExport;
use App\Exports\RealisasiBmSheet;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class ExportSafeCellTest extends TestCase
{
    use RefreshDatabase;

    private function loadSheetBinary(string $binary, string $sheet = 'Laporan Belanja Modal'): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'safe').'.xlsx';
        file_put_contents($path, $binary);
        try {
            $book = IOFactory::load($path);

            return $book->getSheetByName($sheet) ?? $book->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function realisasi(string $noSpk, string $kodering, string $kodeBarang, string $kotaKab): Realisasi
    {
        $sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMKN SafeCell',
            'kota_kab' => $kotaKab,
        ]));

        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => $kodering,
            'bulan_realisasi' => 5,
            'ba_tgl' => '2026-05-10',
            'no_spk' => $noSpk,
            'kode_barang' => $kodeBarang,
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));
    }

    public function test_realisasi_sheet_menetralkan_formula_injection(): void
    {
        // Nilai berbahaya diawali '=' '+' '-' '@' harus diberi apostrof.
        $this->realisasi('=HYPERLINK("x")', '@SUM(1)', '-2+3', 'Kota Bandung');

        $binary = Excel::raw(
            new RealisasiBmExport(Realisasi::with('sekolah')->get(), 'SMKN SafeCell', 2026),
            ExcelWriter::XLSX
        );
        $sheet = $this->loadSheetBinary($binary);

        // Kolom E = no_spk, D = kodering_belanja.
        $this->assertEquals("'=HYPERLINK(\"x\")", $sheet->getCell('E10')->getValue());
        $this->assertEquals("'@SUM(1)", $sheet->getCell('D10')->getValue());
    }

    public function test_safe_cell_mengembalikan_kosong_untuk_null_dan_string_kosong(): void
    {
        $sheet = new RealisasiBmSheet(collect(), 'SMKN', 2026);
        $method = new \ReflectionMethod($sheet, 'safeCell');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($sheet, null));
        $this->assertSame('', $method->invoke($sheet, ''));
        $this->assertSame('aman', $method->invoke($sheet, 'aman'));
        // Nilai aman tidak diubah.
        $this->assertSame('Laptop 2026', $method->invoke($sheet, 'Laptop 2026'));
    }

    public function test_cetak_bm_sheet_format_kota_kab_kosong(): void
    {
        $sheet = new CetakBmSheet(collect(), 'SMKN', 2026);
        $method = new \ReflectionMethod($sheet, 'formatKotaKab');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($sheet, ''));
        $this->assertSame('', $method->invoke($sheet, '   '));
        $this->assertSame('KOTA BANDUNG', $method->invoke($sheet, 'Kota Bandung'));
        $this->assertSame('KABUPATEN BANDUNG BARAT', $method->invoke($sheet, 'Kab. Bandung Barat'));
        $this->assertSame('KABUPATEN SUMEDANG', $method->invoke($sheet, 'Sumedang'));
    }
}
