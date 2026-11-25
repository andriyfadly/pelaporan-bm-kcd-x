<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\PenyaluranMassalRequest;
use App\Http\Requests\PencatatanBm\PenyaluranRequest;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\PencatatanBm\MutasiInventarisService;
use App\Services\PencatatanBm\NomorDokumenInventarisService;
use App\Support\RentangRegister;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buku Penyaluran: daftar barang sekolah dikelompokkan per rentang register
 * (seperti grid `buku_salur.php` acuan), dengan aksi simpan massal, restart
 * penyaluran, edit penerima, dan cetak BAST/Pakta Integritas per baris.
 */
class PenyaluranController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function __construct(private readonly MutasiInventarisService $service) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $search = trim((string) $request->input('search', ''));
        $bulan = (string) $request->input('bulan', '');
        $tahun = (string) $request->input('tahun', '');

        $units = InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap'])
            ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->when($search, fn (Builder $q) => $this->whereAnyLike(
                $q,
                ['nama_barang', 'merk_tipe', 'kode_barang', 'nomor_register'],
                $search,
            ))
            ->when($bulan !== '', fn (Builder $q) => $q->where('bulan_perolehan', (int) $bulan))
            ->when($tahun !== '', fn (Builder $q) => $q->where('tahun_perolehan', (int) $tahun))
            ->orderByDesc('tahun_perolehan')
            ->orderByDesc('bulan_perolehan')
            ->orderBy('nomor_register')
            ->get();

        return Inertia::render('PencatatanBm/Penyaluran/Index', [
            'baris' => $this->susunBaris($units),
            'ruangList' => Ruang::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereNull('deleted_at')
                ->orderBy('nama_ruang')
                ->get(['id', 'nama_ruang']),
            'pegawaiList' => Pegawai::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereNull('deleted_at')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap']),
            'bulanList' => collect(range(1, 12))
                ->map(fn (int $n): array => ['value' => $n, 'label' => now()->month($n)->translatedFormat('F')])
                ->all(),
            'tahunList' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->distinct()
                ->orderByDesc('tahun_perolehan')
                ->pluck('tahun_perolehan'),
            'filter' => ['search' => $search, 'bulan' => $bulan, 'tahun' => $tahun],
            'asalList' => InventarisUnit::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereNotNull('asal_perolehan')
                ->distinct()
                ->orderBy('asal_perolehan')
                ->pluck('asal_perolehan'),
            'kondisiList' => InventarisUnit::KONDISI,
            'nomorBastBerikutnya' => $sekolahId
                ? app(NomorDokumenInventarisService::class)->pratinjau($sekolahId, 'bast')
                : null,
        ]);
    }

    /**
     * Simpan penyaluran untuk banyak unit sekaligus, satu baris = satu rentang
     * register. Semua baris dalam satu transaksi: satu unit yang ditolak
     * membatalkan seluruh penyimpanan supaya tabel tidak setengah tersimpan.
     */
    public function storeMassal(PenyaluranMassalRequest $request): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $jumlah = 0;

        DB::transaction(function () use ($request, $sekolahId, &$jumlah): void {
            foreach ($request->validated()['baris'] as $baris) {
                $units = InventarisUnit::query()
                    ->where('sekolah_id', $sekolahId)
                    ->whereIn('id', $baris['unit_ids'])
                    ->orderBy('nomor_register')
                    ->get();

                if ($units->count() !== count($baris['unit_ids'])) {
                    abort(404);
                }

                // Acuan membolehkan menyalurkan sebagian isi baris (`vol`); unit
                // yang sudah ditempatkan tidak boleh disalurkan ulang.
                $sisa = $units->filter(
                    fn (InventarisUnit $unit): bool => $unit->ruang_id === null && $unit->pegawai_id === null,
                )->values();

                if ($sisa->isEmpty()) {
                    throw ValidationException::withMessages([
                        'unit' => 'Unit sudah disalurkan. Gunakan mutasi untuk memindahkannya.',
                    ]);
                }

                $vol = (int) ($baris['vol'] ?? $sisa->count());

                if ($vol > $sisa->count()) {
                    throw ValidationException::withMessages([
                        'baris' => 'Jumlah yang disalurkan melebihi sisa unit pada baris ini.',
                    ]);
                }

                $units = $sisa->take($vol);

                foreach ($units as $unit) {
                    Gate::authorize('mutasi', $unit);

                    $this->service->salurkan($unit, [
                        'ruang_id' => $baris['ruang_id'],
                        'pegawai_id' => $baris['pegawai_id'] ?? null,
                        'tanggal' => $baris['tanggal'],
                        'keterangan' => $baris['keterangan'] ?? null,
                    ], (string) $request->user()->id);

                    $jumlah++;
                }
            }
        });

        return back()->with('success', "{$jumlah} unit disalurkan.");
    }

    /** Restart penyaluran: unit kembali ke gudang tanpa menerbitkan BA. */
    public function reset(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('mutasi', $unit);

        if ($unit->status === InventarisUnit::STATUS_DIKEMBALIKAN) {
            throw ValidationException::withMessages([
                'unit' => 'Unit sudah dikembalikan, penyaluran tidak bisa direstart.',
            ]);
        }

        DB::transaction(function () use ($request, $unit): void {
            $this->service->koreksi(
                $unit,
                ['keterangan' => 'Penyaluran direset', 'tanggal' => now()->toDateString()],
                (string) $request->user()->id,
            );

            $unit->update([
                'ruang_id' => null,
                'pegawai_id' => null,
                'status' => InventarisUnit::STATUS_AKTIF,
            ]);
        });

        return back()->with('success', 'Penyaluran berhasil direstart.');
    }

    /** Edit penerima/lokasi setelah barang disalurkan (acuan: update_penerima). */
    public function ubahPenerima(PenyaluranRequest $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        Gate::authorize('mutasi', $unit);

        $this->service->mutasikan(
            $unit,
            $request->validated() + ['tanggal' => now()->toDateString()],
            (string) $request->user()->id,
        );

        return back()->with('success', 'Berhasil diupdate!');
    }

    /**
     * Kelompokkan unit menjadi baris Buku Penyaluran: kunci atribut barang, lalu
     * dipotong menjadi run nomor register berurutan.
     *
     * @param  Collection<int, InventarisUnit>  $units
     * @return array<int, array<string, mixed>>
     */
    private function susunBaris($units): array
    {
        $baris = [];

        foreach ($units->groupBy(fn (InventarisUnit $unit): string => implode('|', [
            $unit->kode_barang,
            $unit->tahun_perolehan,
            $unit->bulan_perolehan,
            $unit->tanggal_perolehan?->format('Y-m-d'),
            $unit->harga_satuan,
            $unit->nama_barang,
            $unit->merk_tipe,
            $unit->asal_perolehan,
        ])) as $kelompok) {
            $volumePengadaan = $kelompok->count();

            foreach (RentangRegister::potongRun($kelompok->sortBy('nomor_register')->values()) as $run) {
                $pertama = $run->first();
                $semuaTersalur = $run->every(fn (InventarisUnit $unit): bool => $unit->ruang_id !== null || $unit->pegawai_id !== null);
                $sisa = $run->filter(
                    fn (InventarisUnit $unit): bool => $unit->ruang_id === null && $unit->pegawai_id === null,
                )->values();

                $baris[] = [
                    'unit_ids' => $run->pluck('id')->all(),
                    'kode_barang' => $pertama->kode_barang,
                    'nama_barang' => $pertama->nama_barang,
                    'merk_tipe' => $pertama->merk_tipe,
                    'tanggal_perolehan' => $pertama->tanggal_perolehan?->format('d/m/Y'),
                    'bulan_perolehan' => $pertama->bulan_perolehan,
                    'tahun_perolehan' => $pertama->tahun_perolehan,
                    'volume' => $run->count(),
                    'volume_pengadaan' => $volumePengadaan,
                    'satuan' => $pertama->satuan,
                    'harga_satuan' => (float) $pertama->harga_satuan,
                    'nomor_register' => RentangRegister::dari($run->pluck('nomor_register')->all()),
                    'ruang_id' => $pertama->ruang_id,
                    'pegawai_id' => $pertama->pegawai_id,
                    'keterangan' => $pertama->keterangan,
                    'sudah_disalurkan' => $semuaTersalur,
                    'sisa' => $sisa->count(),
                    'sisa_unit_ids' => $sisa->pluck('id')->all(),
                ];
            }
        }

        return $baris;
    }
}
