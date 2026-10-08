<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpKartuPersediaanExport;
use App\Exports\DistribusiBhp\BhpPengeluaranExport;
use App\Exports\DistribusiBhp\BhpStokExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly LaporanService $laporan,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
        ])['sumber_dana'] ?? 'BOS';
        $sumberDana = strtoupper((string) $sumberDana);

        return Inertia::render('DistribusiBhp/Laporan/Index', [
            'sumberDana' => $sumberDana,
            'stok' => $this->laporan->laporanStok($sumberDana, $sekolahId),
            'pengeluaran' => $this->laporan->bukuPengeluaran($sumberDana, $sekolahId),
        ]);
    }

    public function kartuPersediaan(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(['BOS', 'BOPD'])],
            'barang_id' => ['nullable', 'uuid', 'exists:bhp_barang,id'],
        ]);
        $sumberDana = strtoupper((string) ($validated['sumber_dana'] ?? 'BOS'));
        $barangId = (string) ($validated['barang_id'] ?? '');

        return Inertia::render('DistribusiBhp/Laporan/KartuPersediaan', [
            'sumberDana' => $sumberDana,
            'barangId' => $barangId,
            'kartu' => $barangId !== '' ? $this->laporan->kartuPersediaan($barangId, $sumberDana, $sekolahId) : null,
            'daftarBarang' => Barang::where('sumber_dana', $sumberDana)
                ->orderBy('nama_barang')
                ->get(['id', 'kode_barang', 'nama_barang']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['required', Rule::in(['BOS', 'BOPD'])],
            'jenis' => ['required', Rule::in(['stok', 'pengeluaran', 'kartu_persediaan'])],
            'barang_id' => ['required_if:jenis,kartu_persediaan', 'uuid'],
        ]);

        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = strtoupper($validated['sumber_dana']);
        $jenis = $validated['jenis'];

        if ($jenis === 'stok') {
            $rows = $this->laporan->laporanStok($sumberDana, $sekolahId);
            $export = new BhpStokExport($rows);
            $filename = "laporan_stok_bhp_{$sumberDana}.xlsx";
        } elseif ($jenis === 'kartu_persediaan') {
            try {
                $kartu = $this->laporan->kartuPersediaan($validated['barang_id'], $sumberDana, $sekolahId);
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
            $rows = $kartu['mutasi'];
            $export = new BhpKartuPersediaanExport($kartu['barang'], $rows);
            $filename = "kartu_persediaan_bhp_{$sumberDana}.xlsx";
        } else {
            $rows = $this->laporan->bukuPengeluaran($sumberDana, $sekolahId);
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
