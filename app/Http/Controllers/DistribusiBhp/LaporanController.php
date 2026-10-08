<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpPengeluaranExport;
use App\Exports\DistribusiBhp\BhpStokExport;
use App\Http\Controllers\Controller;
use App\Services\DistribusiBhp\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function __construct(private readonly LaporanService $laporan) {}

    public function index(Request $request): Response
    {
        $sumberDana = strtoupper((string) $request->input('sumber_dana', 'BOS'));
        $barangId = $request->input('barang_id');

        return Inertia::render('DistribusiBhp/Laporan/Index', [
            'sumberDana' => $sumberDana,
            'stok' => $this->laporan->laporanStok($sumberDana),
            'pengeluaran' => $this->laporan->bukuPengeluaran($sumberDana),
            'kartu' => $barangId ? $this->laporan->kartuBarang($barangId) : null,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'jenis' => ['required', Rule::in(['stok', 'pengeluaran'])],
        ]);

        $sumberDana = strtoupper($validated['sumber_dana']);
        $jenis = $validated['jenis'];

        if ($jenis === 'stok') {
            $rows = $this->laporan->laporanStok($sumberDana);
            $export = new BhpStokExport($rows);
            $filename = "laporan_stok_bhp_{$sumberDana}.xlsx";
        } else {
            $rows = $this->laporan->bukuPengeluaran($sumberDana);
            $export = new BhpPengeluaranExport($rows);
            $filename = "buku_pengeluaran_bhp_{$sumberDana}.xlsx";
        }

        activity('sistem')
            ->event('unduh-laporan-bhp')
            ->withProperties([
                'ringkasan' => "Unduh {$filename} (".count($rows).' baris)',
                'sumber_dana' => $sumberDana,
                'jenis' => $jenis,
                'jumlah' => count($rows),
            ])
            ->log('unduh-laporan-bhp');

        return Excel::download($export, $filename);
    }
}
