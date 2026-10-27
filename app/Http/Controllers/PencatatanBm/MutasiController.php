<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\MutasiRequest;
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

class MutasiController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function __construct(private readonly MutasiInventarisService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('PencatatanBm/Mutasi/Index', [
            'riwayat' => Mutasi::query()
                ->with([
                    'unit:id,nomor_register,kode_barang,nama_barang',
                    'asalRuang:id,nama_ruang', 'tujuanRuang:id,nama_ruang',
                    'asalPegawai:id,nama_lengkap', 'tujuanPegawai:id,nama_lengkap',
                    'user:id,name',
                ])
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->latest('tanggal')
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
            'units' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->where('status', InventarisUnit::STATUS_AKTIF)
                ->orderBy('kode_barang')
                ->orderBy('nomor_register')
                ->get(['id', 'nomor_register', 'kode_barang', 'nama_barang', 'sekolah_id']),
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

    public function store(MutasiRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('mutasi', $unit);

        $mutasi = $this->service->tempatkan($unit, $request->validated(), (string) $request->user()->id);

        return back()->with('success', "Unit {$unit->nomor_register} dicatat sebagai {$mutasi->jenis}.");
    }
}
