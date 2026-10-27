<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PencatatanBm\Concerns\TenantScope;
use App\Http\Requests\PencatatanBm\UpdateKondisiRequest;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\PencatatanBm\Pegawai;
use App\Models\PencatatanBm\Ruang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventarisController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $search = trim((string) $request->input('search', ''));
        $kondisi = (string) $request->input('kondisi', '');

        $units = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->when($search, function (Builder $q) use ($search): void {
                $escaped = addcslashes($search, '%_\\');
                $q->where(fn (Builder $w) => $w
                    ->where('nama_barang', 'like', "%{$escaped}%")
                    ->orWhere('kode_barang', 'like', "%{$escaped}%")
                    ->orWhere('nomor_register', 'like', "%{$escaped}%")
                    ->orWhere('merk_tipe', 'like', "%{$escaped}%"));
            })
            ->when(in_array($kondisi, array_keys(InventarisUnit::KONDISI), true),
                fn (Builder $q) => $q->where('kondisi', $kondisi))
            ->orderBy('kode_barang')
            ->orderBy('nomor_register')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('PencatatanBm/Inventaris/Index', [
            'units' => $units,
            'filter' => ['search' => $search, 'kondisi' => $kondisi],
            'kondisiList' => InventarisUnit::KONDISI,
        ]);
    }

    public function show(Request $request, InventarisUnit $unit): Response
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('view', $unit);

        $unit->load([
            'realisasi:id,no_spk,ba_no,ba_tgl,sumber_perolehan',
            'ruang:id,nama_ruang',
            'pegawai:id,nama_lengkap,nip,jabatan',
        ]);

        $mutasi = Mutasi::where('inventaris_unit_id', $unit->id)
            ->with(['asalRuang:id,nama_ruang', 'tujuanRuang:id,nama_ruang', 'asalPegawai:id,nama_lengkap', 'tujuanPegawai:id,nama_lengkap', 'user:id,name'])
            ->latest('tanggal')
            ->latest('created_at')
            ->get();

        return Inertia::render('PencatatanBm/Inventaris/Show', [
            'unit' => $unit,
            'mutasi' => $mutasi,
            'ruangList' => Ruang::where('sekolah_id', $unit->sekolah_id)->orderBy('nama_ruang')->get(['id', 'nama_ruang']),
            'pegawaiList' => Pegawai::where('sekolah_id', $unit->sekolah_id)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'kondisiList' => InventarisUnit::KONDISI,
        ]);
    }

    public function updateKondisi(UpdateKondisiRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('update', $unit);

        $unit->update($request->validated());

        return back()->with('success', "Kondisi unit {$unit->nomor_register} diperbarui.");
    }
}
