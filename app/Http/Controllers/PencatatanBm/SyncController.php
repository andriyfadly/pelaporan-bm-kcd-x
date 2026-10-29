<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\SyncLog;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SyncController extends Controller
{
    use ResolvesSekolah;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        // Unit di luar volume realisasi yang sudah dimutasi: sengaja tidak
        // dipensiunkan otomatis, jadi muncul sebagai daftar tinjau manual.
        $perluReview = InventarisUnit::query()
            ->with('realisasi:id,volume')
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->whereHas('mutasi')
            ->whereHas('realisasi', fn (Builder $q) => $q->whereColumn('pelaporan_bm_realisasi.volume', '<', 'inventaris_unit.unit_index'))
            ->orderBy('kode_barang')
            ->orderBy('tahun_perolehan')
            ->orderBy('nomor_register')
            ->get(['id', 'sekolah_id', 'realisasi_id', 'unit_index', 'kode_barang', 'nomor_register', 'nama_barang']);

        return Inertia::render('PencatatanBm/Sinkronisasi/Index', [
            'logs' => SyncLog::query()
                ->when($sekolahId, fn ($q) => $q->where('sekolah_id', $sekolahId))
                ->latest('created_at')
                ->limit(50)
                ->get(),
            'perluReview' => $perluReview,
            'sekolahList' => $sekolahId === null
                ? Sekolah::orderBy('nama_sekolah')->get(['id', 'nama_sekolah'])
                : [],
            'filter' => ['sekolah_id' => $sekolahId],
        ]);
    }

    public function store(Request $request, SinkronisasiInventarisService $service): RedirectResponse
    {
        $validated = $request->validate([
            'sekolah_id' => ['required', 'uuid', 'exists:master_data_sekolah,id'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2026,9999'],
        ]);

        try {
            $hasil = $service->syncPeriode(
                $validated['sekolah_id'],
                (int) $validated['bulan'],
                (int) $validated['tahun'],
                (string) $request->user()->id,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Sinkronisasi gagal: {$e->getMessage()}");
        }

        return back()->with('success', sprintf(
            'Sinkronisasi selesai: %d dibuat, %d tetap, %d surplus, %d perlu review.',
            $hasil->dibuat, $hasil->tetap, $hasil->surplus, $hasil->perluReview,
        ));
    }
}
