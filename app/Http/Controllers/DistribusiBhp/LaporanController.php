<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Http\Controllers\Controller;
use App\Services\DistribusiBhp\LaporanService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
