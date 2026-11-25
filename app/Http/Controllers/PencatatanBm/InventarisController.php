<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Exports\InventarisExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\UpdateInventarisRequest;
use App\Http\Requests\PencatatanBm\UpdateKondisiRequest;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\PencatatanBm\MutasiInventarisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventarisController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function __construct(private readonly MutasiInventarisService $mutasi) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $search = trim((string) $request->input('search', ''));
        $tahun = (string) $request->input('tahun', '');
        $asal = (string) $request->input('asal', '');

        $units = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap', 'fotoTerbaru:id,inventaris_unit_id'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('status', $this->statusTampil())
            ->when($search, fn (Builder $q) => $this->whereAnyLike(
                $q,
                ['nama_barang', 'kode_barang', 'nomor_register', 'merk_tipe'],
                $search,
            ))
            ->when($tahun !== '', fn (Builder $q) => $q->where('tahun_perolehan', (int) $tahun))
            ->when($asal !== '', fn (Builder $q) => $q->where('asal_perolehan', $asal))
            ->orderBy('tahun_perolehan')
            ->orderBy('bulan_perolehan')
            ->orderBy('tanggal_perolehan')
            ->orderBy('nomor_register')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('PencatatanBm/Inventaris/Index', [
            'units' => $units,
            'filter' => ['search' => $search, 'tahun' => $tahun, 'asal' => $asal],
            'tahunList' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereIn('status', $this->statusTampil())
                ->distinct()
                ->orderByDesc('tahun_perolehan')
                ->pluck('tahun_perolehan'),
            'asalList' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereIn('status', $this->statusTampil())
                ->whereNotNull('asal_perolehan')
                ->distinct()
                ->orderBy('asal_perolehan')
                ->pluck('asal_perolehan'),
            'kondisiList' => InventarisUnit::KONDISI,
            'canKelola' => $request->user()->can('kelola-inventaris-bm'),
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
            'fotoTerbaru:id,inventaris_unit_id',
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

    /** Kartu barang hasil pindai QR: halaman cetak identitas + foto aset. */
    public function kartu(Request $request, InventarisUnit $unit): \Symfony\Component\HttpFoundation\Response
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);

        $unit->load(['sekolah', 'katalog']);

        $pdf = Pdf::loadView('pencatatan-bm.kartu-barang', [
            'judul' => 'Kartu Barang',
            'unit' => $unit,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Kartu-Barang-'.$unit->nomor_register.'.pdf');
    }

    public function updateKondisi(UpdateKondisiRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('update', $unit);

        // Lewat service supaya koreksi kondisi/penempatan juga meninggalkan
        // histori; jenisnya (koreksi/penyaluran/mutasi) ditentukan dari state unit.
        $this->mutasi->koreksi(
            $unit,
            $request->validated() + ['tanggal' => now()->toDateString()],
            (string) $request->user()->id,
        );

        if ($request->has('keterangan')) {
            $unit->update(['keterangan' => $request->validated()['keterangan']]);
        }

        return back()->with('success', "Kondisi unit {$unit->nomor_register} diperbarui.");
    }

    /**
     * `Ubah Semua Sekaligus` dari acuan: pilih banyak unit, satu kondisi untuk
     * semua, tetap tercatat sebagai koreksi per unit.
     */
    public function updateKondisiMassal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit_ids' => ['required', 'array', 'min:1', 'max:200'],
            'unit_ids.*' => ['required', 'uuid', 'exists:inventaris_unit,id'],
            'kondisi' => ['required', 'array'],
            'kondisi.*' => [Rule::in(array_keys(InventarisUnit::KONDISI))],
        ]);

        $sekolahId = $this->resolveSekolahIdWajib($request);

        $units = InventarisUnit::query()
            ->where('sekolah_id', $sekolahId)
            ->whereIn('id', $validated['unit_ids'])
            ->get();

        if ($units->count() !== count($validated['unit_ids'])) {
            abort(404);
        }

        $tanpaKondisi = array_diff($validated['unit_ids'], array_keys($validated['kondisi']));

        if ($tanpaKondisi !== []) {
            throw ValidationException::withMessages([
                'kondisi' => 'Setiap unit yang dipilih harus punya nilai kondisi.',
            ]);
        }

        DB::transaction(function () use ($units, $validated, $request): void {
            foreach ($units as $unit) {
                $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
                Gate::authorize('update', $unit);

                $kondisi = $validated['kondisi'][$unit->id];

                if ($kondisi === $unit->kondisi) {
                    continue;
                }

                $this->mutasi->koreksi(
                    $unit,
                    ['kondisi' => $kondisi, 'tanggal' => now()->toDateString()],
                    (string) $request->user()->id,
                );
            }
        });

        return back()->with('success', 'Kondisi detail seluruh unit berhasil disimpan!');
    }

    /**
     * Edit data barang satu unit. Register dan volume tidak berubah: nomor yang
     * sudah terbit tidak boleh bergeser karena koreksi data.
     */
    public function update(UpdateInventarisRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('update', $unit);

        $validated = $request->validated();
        $tanggal = Carbon::parse($validated['tanggal_perolehan']);

        $this->pastikanRegisterTersedia($unit, $validated['kode_barang'], (int) $tanggal->year);

        $kondisiBaru = $validated['kondisi'] ?? null;
        unset($validated['kondisi'], $validated['foto']);

        $atribut = $validated + [
            'tahun_perolehan' => $tanggal->year,
            'bulan_perolehan' => $tanggal->month,
            'harga_satuan' => (float) ($validated['harga_satuan'] ?? 0),
            'nilai_perolehan_unit' => (float) ($validated['nilai_perolehan_unit'] ?? $validated['harga_satuan'] ?? 0),
        ];

        DB::transaction(function () use ($unit, $atribut, $kondisiBaru, $request): void {
            $unit->update($atribut);

            if ($kondisiBaru !== null && $kondisiBaru !== $unit->kondisi) {
                $this->mutasi->koreksi(
                    $unit,
                    ['kondisi' => $kondisiBaru, 'tanggal' => now()->toDateString()],
                    (string) $request->user()->id,
                );
            }
        });

        if ($request->hasFile('foto')) {
            app(UnitInventarisController::class)->storeFoto($request, $unit->fresh());
        }

        return back()->with('success', 'Data barang berhasil diperbarui.');
    }

    /** Hapus satu unit dari daftar: histori tetap, unit ditandai `dihapus_manual`. */
    public function destroy(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('update', $unit);

        DB::transaction(function () use ($unit, $request): void {
            if ($unit->mutasi()->exists()) {
                $this->mutasi->koreksi(
                    $unit,
                    ['keterangan' => 'Barang dihapus dari daftar inventaris', 'tanggal' => now()->toDateString()],
                    (string) $request->user()->id,
                );
            }

            $unit->update([
                'status' => InventarisUnit::STATUS_DIHAPUS_MANUAL,
                'ruang_id' => null,
                'pegawai_id' => null,
            ]);
        });

        return back()->with('success', 'Data barang berhasil dihapus.');
    }

    public function destroySemua(Request $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $request->validate([
            'konfirmasi' => ['required', Rule::in(['HAPUS SEMUA'])],
        ]);

        $jumlah = DB::transaction(fn (): int => InventarisUnit::query()
            ->where('sekolah_id', $sekolahId)
            ->whereIn('status', $this->statusTampil())
            ->update([
                'status' => InventarisUnit::STATUS_DIHAPUS_MANUAL,
                'ruang_id' => null,
                'pegawai_id' => null,
                'updated_at' => now(),
            ]));

        return back()->with(
            'success',
            $jumlah > 0 ? 'Seluruh data barang berhasil dihapus.' : 'Tidak ada data barang yang bisa dihapus.',
        );
    }

    /** Pratinjau nomor register berikutnya (tidak menaikkan sequence). */
    public function nextRegister(Request $request): JsonResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);

        $validated = $request->validate([
            'kode_barang' => ['required', 'string', 'max:100'],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:2200'],
        ]);

        $tahun = (int) ($validated['tahun'] ?? now()->year);

        $berikutnya = InventarisRegisterSequence::query()
            ->where('sekolah_id', $sekolahId)
            ->where('kode_barang', $validated['kode_barang'])
            ->where('tahun', $tahun)
            ->value('next_number') ?? 1;

        return response()->json([
            'register' => str_pad((string) $berikutnya, 3, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Register tidak boleh bertabrakan saat kode barang atau tahun perolehan
     * diubah — unique index-nya (sekolah, kode, tahun, register).
     */
    private function pastikanRegisterTersedia(InventarisUnit $unit, string $kodeBaru, int $tahunBaru): void
    {
        if ($kodeBaru === $unit->kode_barang && $tahunBaru === (int) $unit->tahun_perolehan) {
            return;
        }

        $bentrok = InventarisUnit::query()
            ->where('sekolah_id', $unit->sekolah_id)
            ->where('kode_barang', $kodeBaru)
            ->where('tahun_perolehan', $tahunBaru)
            ->where('nomor_register', $unit->nomor_register)
            ->whereKeyNot($unit->id)
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'kode_barang' => "Nomor register {$unit->nomor_register} sudah dipakai untuk kode barang ini pada tahun {$tahunBaru}.",
            ]);
        }
    }

    /** @return array<int, string> */
    private function statusTampil(): array
    {
        return [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN];
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
            ->orderBy('tahun_perolehan')
            ->orderBy('nomor_register');

        return Excel::download(
            new InventarisExport($query),
            'inventaris-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
