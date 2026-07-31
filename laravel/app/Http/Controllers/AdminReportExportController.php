<?php

namespace App\Http\Controllers;

use App\Models\RealisasiBarangSekolah;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $rows = RealisasiBarangSekolah::query()
            ->leftJoin('kode_sekolah', 'realisasi_barang_sekolah.id_sekolah', '=', 'kode_sekolah.id_sekolah')
            ->where('realisasi_barang_sekolah.bulan_realisasi', $data['bulan'])
            ->whereYear('realisasi_barang_sekolah.ba_tgl', $data['tahun'])
            ->orderBy('kode_sekolah.nama_sekolah')
            ->orderBy('realisasi_barang_sekolah.ba_tgl')
            ->get([
                'kode_sekolah.nama_sekolah',
                'realisasi_barang_sekolah.ba_tgl',
                'realisasi_barang_sekolah.no_spk',
                'realisasi_barang_sekolah.nama_barang',
                'realisasi_barang_sekolah.volume',
                'realisasi_barang_sekolah.harga_satuan',
                'realisasi_barang_sekolah.nilai_perolehan',
            ]);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Nama Sekolah', 'Tanggal BA', 'No. SPK', 'Nama Barang', 'Volume', 'Harga Satuan', 'Nilai Perolehan']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $this->safeCell($row->nama_sekolah),
                    $row->ba_tgl,
                    $this->safeCell($row->no_spk),
                    $this->safeCell($row->nama_barang),
                    $row->volume,
                    $row->harga_satuan,
                    $row->nilai_perolehan,
                ]);
            }

            fclose($output);
        }, sprintf('belanja-modal-%d-%02d.csv', $data['tahun'], $data['bulan']), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function safeCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }
}
