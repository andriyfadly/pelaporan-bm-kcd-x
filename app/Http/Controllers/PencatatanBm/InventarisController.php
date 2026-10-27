<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Exports\InventarisExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\UpdateKondisiRequest;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventarisController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $search = trim((string) $request->input('search', ''));
        $kondisi = (string) $request->input('kondisi', '');

        $units = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->when($search, fn (Builder $q) => $this->whereAnyLike(
                $q,
                ['nama_barang', 'kode_barang', 'nomor_register', 'merk_tipe'],
                $search,
            ))
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
            'pegawai:id,nama_lengkap,nip',
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

    public function export(Request $request): BinaryFileResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $kondisi = (string) $request->input('kondisi', '');

        $query = InventarisUnit::query()
            ->where('sekolah_id', $sekolahId)
            ->when(in_array($kondisi, array_keys(InventarisUnit::KONDISI), true),
                fn (Builder $q) => $q->where('kondisi', $kondisi))
            ->orderBy('kode_barang')
            ->orderBy('nomor_register');

        return Excel::download(
            new InventarisExport($query),
            'inventaris-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
