<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\PengembalianRequest;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\PencatatanBm\MutasiInventarisService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PengembalianController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function __construct(private readonly MutasiInventarisService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('PencatatanBm/Pengembalian/Index', [
            'dipinjam' => InventarisUnit::query()
                ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->where('status', InventarisUnit::STATUS_AKTIF)
                ->where(fn (Builder $q) => $q->whereNotNull('ruang_id')->orWhereNotNull('pegawai_id'))
                ->orderBy('kode_barang')
                ->orderBy('nomor_register')
                ->get(),
            'riwayat' => Mutasi::query()
                ->with(['unit:id,nomor_register,nama_barang', 'asalRuang:id,nama_ruang', 'asalPegawai:id,nama_lengkap', 'user:id,name'])
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->where('jenis', 'pengembalian')
                ->latest('tanggal')
                ->latest('created_at')
                ->limit(50)
                ->get(),
            'kondisiList' => InventarisUnit::KONDISI,
        ]);
    }

    public function store(PengembalianRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('kembalikan', $unit);

        $this->service->kembalikan($unit, $request->validated(), (string) $request->user()->id);

        return back()->with('success', "Unit {$unit->nomor_register} dikembalikan.");
    }
}
