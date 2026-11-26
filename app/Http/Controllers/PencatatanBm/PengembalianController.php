<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\PengembalianRequest;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\MutasiInventarisService;
use App\Services\PencatatanBm\NomorDokumenInventarisService;
use App\Support\RentangRegister;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengembalian barang dari penerima, satu baris Buku Penyaluran (barang +
 * penerima + rentang register) per Berita Acara seperti acuan.
 */
class PengembalianController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function __construct(
        private readonly MutasiInventarisService $service,
        private readonly DokumenInventarisService $dokumen,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);

        $petaBast = $this->dokumen->petaBastAktif((string) $sekolahId);

        $units = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->where(fn (Builder $q) => $q->whereNotNull('ruang_id')->orWhereNotNull('pegawai_id'))
            ->orderBy('kode_barang')
            ->orderBy('tahun_perolehan')
            ->orderBy('nomor_register')
            ->get()
            // Acuan hanya menampilkan penyaluran yang sudah punya BAST aktif.
            ->filter(fn (InventarisUnit $unit): bool => isset($petaBast[$unit->id]))
            ->values();

        $riwayat = Mutasi::query()
            ->with(['unit:id,nomor_register,nama_barang,merk_tipe,tahun_perolehan', 'asalRuang:id,nama_ruang', 'asalPegawai:id,nama_lengkap'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->where('jenis', 'pengembalian')
            ->latest('tanggal')
            ->latest('created_at')
            ->get();

        // Satu dokumen BA mencakup banyak mutasi; petakan lewat payload.mutasi_ids.
        $dokumenPengembalian = Dokumen::query()
            ->where('sekolah_id', $sekolahId ?? '')
            ->where('jenis', 'pengembalian')
            ->get(['id', 'nomor', 'tanggal', 'payload']);

        $petaMutasi = [];
        foreach ($dokumenPengembalian as $dokumen) {
            foreach (($dokumen->payload['mutasi_ids'] ?? []) as $mutasiId) {
                $petaMutasi[$mutasiId] ??= ['dokumen_id' => $dokumen->id, 'nomor' => $dokumen->nomor];
            }
        }

        return Inertia::render('PencatatanBm/Pengembalian/Index', [
            'baris' => $this->susunBaris($units, $petaBast),
            'riwayatRingkas' => $riwayat->take(5)->map(fn (Mutasi $mutasi): array => $this->barisRiwayat($mutasi, $petaMutasi))->all(),
            'riwayat' => $riwayat->map(fn (Mutasi $mutasi): array => $this->barisRiwayat($mutasi, $petaMutasi))->all(),
            'kondisiList' => InventarisUnit::KONDISI,
            'canProsesPengembalian' => $request->user()->can('proses-pengembalian-bm'),
            'nomorBerikutnya' => $sekolahId
                ? app(NomorDokumenInventarisService::class)->pratinjau($sekolahId, 'pengembalian')
                : null,
        ]);
    }

    /**
     * @param  array<string, array{dokumen_id: string, nomor: string|null}>  $petaMutasi
     * @return array<string, mixed>
     */
    private function barisRiwayat(Mutasi $mutasi, array $petaMutasi): array
    {
        return [
            'id' => $mutasi->id,
            'tanggal' => $mutasi->tanggal->format('Y-m-d'),
            'waktu' => $mutasi->created_at?->toIso8601String(),
            'kode_barang' => $mutasi->unit?->kode_barang,
            'nomor_register' => $mutasi->unit?->nomor_register,
            'nama_barang' => $mutasi->unit?->nama_barang,
            'merk_tipe' => $mutasi->unit?->merk_tipe,
            'tahun_perolehan' => $mutasi->unit?->tahun_perolehan,
            'asal_pegawai' => $mutasi->asalPegawai?->nama_lengkap,
            'asal_ruang' => $mutasi->asalRuang?->nama_ruang,
            'volume' => 1,
            'kondisi_akhir' => $mutasi->tujuan_kondisi,
            'dokumen_id' => $petaMutasi[$mutasi->id]['dokumen_id'] ?? null,
            'nomor' => $petaMutasi[$mutasi->id]['nomor'] ?? null,
        ];
    }

    public function store(PengembalianRequest $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $validated = $request->validated();

        $units = InventarisUnit::query()
            ->with(['sekolah', 'pegawai', 'ruang'])
            ->where('sekolah_id', $sekolahId)
            ->whereIn('id', $validated['unit_ids'])
            ->orderBy('nomor_register')
            ->get();

        if ($units->count() !== count($validated['unit_ids'])) {
            abort(404);
        }

        foreach ($units as $unit) {
            Gate::authorize('kembalikan', $unit);
        }

        $hasil = $this->service->kembalikanBanyak($units, [
            'kondisi' => $validated['kondisi'],
            'tanggal' => $validated['tanggal'],
            'keterangan' => $validated['keterangan'] ?? null,
        ], (string) $request->user()->id, fn (Collection $terkunci): array => $this->dokumen->petaBastAktif(
            $sekolahId,
            $terkunci->pluck('id')->all(),
        ));

        $rentang = RentangRegister::dari($units->pluck('nomor_register')->all());

        return back()->with(
            'success',
            "Unit {$rentang} dikembalikan. Berita Acara Pengembalian {$hasil['dokumen']->nomor} dibuat.",
        )->with('dokumen_id', $hasil['dokumen']->id);
    }

    /**
     * Kelompokkan unit menjadi baris pengembalian: kunci barang + penerima,
     * dipotong per run nomor register (sama seperti Buku Penyaluran).
     *
     * @param  Collection<int, InventarisUnit>  $units
     * @param  array<string, string>  $nomorBast
     * @return array<int, array<string, mixed>>
     */
    private function susunBaris(Collection $units, array $nomorBast): array
    {
        $baris = [];

        foreach ($units->groupBy(fn (InventarisUnit $unit): string => implode('|', [
            $unit->kode_barang,
            $unit->tahun_perolehan,
            $unit->pegawai_id,
            $unit->ruang_id,
        ])) as $kelompok) {
            foreach (RentangRegister::potongRun($kelompok->sortBy('nomor_register')->values()) as $run) {
                $pertama = $run->first();
                $nomorRun = $run
                    ->map(fn (InventarisUnit $unit): ?string => $nomorBast[$unit->id] ?? null)
                    ->unique();

                $baris[] = [
                    'unit_ids' => $run->pluck('id')->all(),
                    'unit_registers' => $run->map(fn (InventarisUnit $unit): array => [
                        'id' => $unit->id,
                        'nomor_register' => $unit->nomor_register,
                    ])->values()->all(),
                    'kode_barang' => $pertama->kode_barang,
                    'nama_barang' => $pertama->nama_barang,
                    'merk_tipe' => $pertama->merk_tipe,
                    'tahun_perolehan' => $pertama->tahun_perolehan,
                    'nomor_register' => RentangRegister::dari($run->pluck('nomor_register')->all()),
                    'volume' => $run->count(),
                    'satuan' => $pertama->satuan,
                    'harga_satuan' => (float) $pertama->harga_satuan,
                    'ruang' => $pertama->ruang?->nama_ruang,
                    'pegawai' => $pertama->pegawai?->nama_lengkap,
                    'pegawai_id' => $pertama->pegawai_id,
                    'nomor_bast_terakhir' => $nomorRun->count() === 1 ? $nomorRun->first() : null,
                ];
            }
        }

        return $baris;
    }
}
