<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\PenyaluranRequest;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\PencatatanBm\MutasiInventarisService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penyaluran: menempatkan barang inventaris ke lokasi/penerima untuk pertama
 * kali. Perpindahan setelahnya ada di menu Mutasi.
 */
class PenyaluranController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function __construct(private readonly MutasiInventarisService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('PencatatanBm/Penyaluran/Index', [
            'riwayat' => Mutasi::query()
                ->with([
                    'unit:id,nomor_register,kode_barang,nama_barang',
                    'tujuanRuang:id,nama_ruang',
                    'tujuanPegawai:id,nama_lengkap',
                    'user:id,name',
                ])
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->where('jenis', 'penyaluran')
                ->latest('tanggal')
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
            // Hanya unit yang belum punya lokasi: begitu disalurkan, jalan
            // berikutnya adalah mutasi. Unit hasil pengembalian kembali masuk
            // daftar ini karena barangnya sudah kembali ke gudang.
            'belumDisalurkan' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
                ->whereNull('ruang_id')
                ->whereNull('pegawai_id')
                ->orderBy('kode_barang')
                ->orderBy('tahun_perolehan')
                ->orderBy('nomor_register')
                ->get(['id', 'nomor_register', 'kode_barang', 'nama_barang', 'tahun_perolehan', 'status']),
            'ruangList' => Ruang::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->orderBy('nama_ruang')
                ->get(['id', 'nama_ruang']),
            'pegawaiList' => Pegawai::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap']),
            'kondisiList' => InventarisUnit::KONDISI,
        ]);
    }

    public function store(PenyaluranRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('mutasi', $unit);

        $this->service->salurkan($unit, $request->validated(), (string) $request->user()->id);

        return back()->with('success', "Unit {$unit->nomor_register} disalurkan.");
    }
}
