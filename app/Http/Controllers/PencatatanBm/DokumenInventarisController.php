<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\SearchesText;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencatatanBm\BastBarisRequest;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Services\Master\PenugasanPegawaiService;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\NomorDokumenInventarisService;
use App\Services\PencatatanBm\QrLabelService;
use App\Support\NamaGelar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DokumenInventarisController extends Controller
{
    use ResolvesSekolah, SearchesText, TenantScope;

    public function __construct(
        private readonly DokumenInventarisService $dokumen,
        private readonly QrLabelService $qr,
        private readonly NomorDokumenInventarisService $nomor,
    ) {}

    public function kir(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $ruang = Ruang::where('sekolah_id', $sekolahId)->findOrFail($request->input('ruang'));

        $validated = $request->validate([
            'show_ks' => ['nullable', 'boolean'],
            'show_wks' => ['nullable', 'boolean'],
            'show_pb' => ['nullable', 'boolean'],
            'show_pj' => ['nullable', 'boolean'],
            'tanggal' => ['nullable', 'date'],
            'penanggung_jawab' => ['nullable', 'uuid'],
        ]);

        $units = $this->unitScope($sekolahId)->where('ruang_id', $ruang->id)->get();

        return $this->dokumen->kirRuang($ruang, $units, [
            'show_ks' => (bool) ($validated['show_ks'] ?? false),
            'show_wks' => (bool) ($validated['show_wks'] ?? false),
            'show_pb' => (bool) ($validated['show_pb'] ?? false),
            'show_pj' => (bool) ($validated['show_pj'] ?? false),
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'penanggung_jawab' => $validated['penanggung_jawab'] ?? null,
        ]);
    }

    public function bukuInventaris(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $tahun = (int) ($request->input('tahun') ?: 0);
        $semester = (int) ($request->input('semester') ?: 0);

        if (! in_array($semester, [0, 1, 2], true)) {
            $semester = 0;
        }

        $tanggalCetak = (string) ($request->input('tanggal') ?: now()->toDateString());

        return $this->dokumen->bukuInventaris(
            $this->sekolah($sekolahId),
            $this->unitScope($sekolahId)
                ->when($tahun > 0, fn (Builder $q) => $q->where('tahun_perolehan', $tahun))
                ->when($semester === 1, fn (Builder $q) => $q->whereBetween('bulan_perolehan', [1, 6]))
                ->when($semester === 2, fn (Builder $q) => $q->whereBetween('bulan_perolehan', [7, 12]))
                // Acuan (buku_inventaris_pdf.php) membuang barang bertanggal
                // setelah tanggal cetak; barang tanpa tanggal tetap tercetak.
                ->where(fn (Builder $q) => $q
                    ->whereNull('tanggal_perolehan')
                    ->orWhereDate('tanggal_perolehan', '<=', $tanggalCetak))
                ->orderBy('tahun_perolehan')
                ->orderBy('bulan_perolehan')
                ->get(),
            $tahun,
            $tanggalCetak,
        );
    }

    public function sensus(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $search = trim((string) $request->input('search', ''));

        $units = InventarisUnit::query()
            ->with(['pegawai:id,nama_lengkap,nip', 'ruang:id,nama_ruang'])
            ->where('sekolah_id', $sekolahId)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->whereNotNull('pegawai_id')
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search): void {
                $this->whereAnyLike($q, ['nama_barang', 'merk_tipe', 'nomor_register'], $search);
                $q->orWhereHas('pegawai', fn (Builder $p) => $p->whereLike('nama_lengkap', $this->likePattern($search)));
            }))
            ->orderBy('nama_barang')
            ->orderBy('nomor_register')
            ->get();

        return $this->dokumen->sensus($this->sekolah($sekolahId), $units, $search);
    }

    public function bast(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        $validated = $request->validate([
            'nomor' => ['nullable', 'string', 'max:150', Rule::unique('inventaris_dokumen', 'nomor')
                ->where('sekolah_id', $unit->sekolah_id)
                ->where('jenis', 'bast')
                ->whereNull('nomor_dibatalkan_pada')],
            'tanggal' => ['nullable', 'date'],
        ]);

        $dokumen = DB::transaction(function () use ($request, $unit, $validated): Dokumen {
            $dokumen = Dokumen::create([
                'sekolah_id' => $unit->sekolah_id,
                'jenis' => 'bast',
                'nomor' => $validated['nomor'] ?? $this->nomor->berikutnya($unit->sekolah_id, 'bast'),
                'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
                'inventaris_unit_id' => $unit->id,
                'inventaris_pegawai_id' => $unit->pegawai_id,
                'payload' => $this->payloadDasar($unit->fresh(), $request, $validated['tanggal'] ?? null),
                'user_id' => $request->user()->id,
            ]);

            return $this->dokumen->simpan($dokumen, 'pencatatan-bm.bast');
        });

        return back()->with('success', 'BAST dibuat.')->with('dokumen_id', $dokumen->id);
    }

    public function faktaIntegritas(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $pegawai->sekolah_id);
        $validated = $request->validate(['tanggal' => ['nullable', 'date']]);

        $units = $this->unitScope($pegawai->sekolah_id)
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->get();
        $tanggal = $validated['tanggal'] ?? now()->toDateString();

        // Acuan (buku_salur.php) menolak Fakta Integritas bila BAST belum ada,
        // atau bila seluruh BAST yang ada sudah pernah dipakai Fakta sebelumnya:
        // satu BAST hanya boleh mendasari satu pakta.
        $unitIds = $units->pluck('id')->all();

        $jumlahBast = Dokumen::query()
            ->where('sekolah_id', $pegawai->sekolah_id)
            ->where('jenis', 'bast')
            ->whereNotNull('nomor')
            ->whereNull('nomor_dibatalkan_pada')
            ->where(fn (Builder $q) => $q
                ->where('inventaris_pegawai_id', $pegawai->id)
                ->orWhereIn('inventaris_unit_id', $unitIds))
            ->count();

        if ($jumlahBast === 0) {
            throw ValidationException::withMessages([
                'unit' => 'Kamu harus men-generate BAST terlebih dahulu sebelum bisa Generate FAKTA!',
            ]);
        }

        $jumlahFakta = Dokumen::query()
            ->where('sekolah_id', $pegawai->sekolah_id)
            ->where('jenis', 'fakta_integritas')
            ->where('inventaris_pegawai_id', $pegawai->id)
            ->count();

        if ($jumlahBast <= $jumlahFakta) {
            throw ValidationException::withMessages([
                'unit' => "Kamu harus men-generate BAST TERBARU untuk '{$pegawai->nama_lengkap}' terlebih dahulu! (BAST sebelumnya sudah pernah digunakan).",
            ]);
        }

        $dokumen = DB::transaction(function () use ($request, $pegawai, $units, $tanggal): Dokumen {
            $dokumen = Dokumen::create([
                'sekolah_id' => $pegawai->sekolah_id,
                'jenis' => 'fakta_integritas',
                'nomor' => $this->nomor->berikutnya($pegawai->sekolah_id, 'fakta_integritas'),
                'tanggal' => $tanggal,
                'inventaris_pegawai_id' => $pegawai->id,
                'payload' => [
                    'sekolah' => $this->snapshotSekolah($pegawai->sekolah),
                    'pegawai' => $this->snapshotPegawai($pegawai, $tanggal),
                    'units' => $units->map(fn (InventarisUnit $unit) => $this->qr->snapshotUnit($unit))->all(),
                    'penerima' => $this->snapshotPenerima($pegawai),
                    'nama_format' => NamaGelar::format($pegawai->nama_lengkap),
                    'pejabat' => $this->dokumen->pejabat($pegawai->sekolah_id, $tanggal),
                    'dicetak_oleh' => $request->user()->name,
                ],
                'user_id' => $request->user()->id,
            ]);

            return $this->dokumen->simpan($dokumen, 'pencatatan-bm.fakta-integritas');
        });

        return back()->with('success', 'Fakta integritas dibuat.')->with('dokumen_id', $dokumen->id);
    }

    /**
     * Surat permohonan pengembalian per pegawai: satu surat memuat seluruh aset
     * yang masih tercatat pada pegawai itu (acuan: surat_permohonan_kembali.php).
     */
    public function suratPengembalianPegawai(Request $request, Pegawai $pegawai): Response
    {
        $this->pastikanSekolahSendiri($request, $pegawai->sekolah_id);

        $validated = $request->validate([
            'nomor' => ['required', 'string', 'max:150'],
            'tanggal' => ['required', 'date'],
        ]);

        $units = $this->unitScope($pegawai->sekolah_id)
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
            ->get();

        $dokumen = DB::transaction(fn (): Dokumen => Dokumen::create([
            'sekolah_id' => $pegawai->sekolah_id,
            'jenis' => 'surat_pengembalian',
            'nomor' => $validated['nomor'],
            'tanggal' => $validated['tanggal'],
            'inventaris_pegawai_id' => $pegawai->id,
            'payload' => [
                'sekolah' => $this->snapshotSekolah($pegawai->sekolah),
                'pegawai' => $this->snapshotPegawai($pegawai, $validated['tanggal']),
                'penerima' => $this->snapshotPenerima($pegawai),
                'nama_format' => NamaGelar::format($pegawai->nama_lengkap),
                'units' => $units->map(fn (InventarisUnit $unit) => $this->qr->snapshotUnit($unit))->all(),
                'pejabat' => $this->dokumen->pejabat($pegawai->sekolah_id, $validated['tanggal']),
                'dicetak_oleh' => $request->user()->name,
            ],
            'user_id' => $request->user()->id,
        ]));

        $this->dokumen->simpan($dokumen, 'pencatatan-bm.surat-pengembalian');

        return $this->dokumen->unduh($dokumen->fresh());
    }

    /**
     * BAST untuk banyak unit sekaligus (satu baris Buku Penyaluran = satu
     * rentang register). Unit di dalam satu baris selalu satu sekolah.
     */
    public function bastBaris(BastBarisRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $tanggal = $validated['tanggal'] ?? now()->toDateString();

        $units = InventarisUnit::query()
            ->with(['pegawai', 'ruang'])
            ->where('sekolah_id', $sekolahId)
            ->whereIn('id', $validated['unit_ids'])
            ->orderBy('nomor_register')
            ->get();

        if ($units->count() !== count($validated['unit_ids'])) {
            abort(404);
        }

        $belum = $units->contains(fn (InventarisUnit $unit): bool => $unit->ruang_id === null && $unit->pegawai_id === null);

        if ($belum) {
            throw ValidationException::withMessages([
                'unit_ids' => 'Baris ini belum disalurkan. Simpan penyaluran lebih dulu.',
            ]);
        }

        $penerima = $units->first()->pegawai;

        $dokumen = DB::transaction(fn (): Dokumen => Dokumen::create([
            'sekolah_id' => $sekolahId,
            'jenis' => 'bast',
            'nomor' => $validated['nomor'] ?? $this->nomor->berikutnya($sekolahId, 'bast'),
            'tanggal' => $tanggal,
            'inventaris_pegawai_id' => $penerima?->id,
            'payload' => [
                'sekolah' => $this->snapshotSekolah(Sekolah::find($sekolahId)),
                'units' => $units->map(fn (InventarisUnit $unit) => $this->qr->snapshotUnit($unit))->all(),
                'penerima' => $this->snapshotPenerima($penerima),
                'nama_format' => NamaGelar::format($penerima?->nama_lengkap),
                'pejabat' => $this->dokumen->pejabat($sekolahId, $tanggal),
                'dicetak_oleh' => $request->user()->name,
            ],
            'user_id' => $request->user()->id,
        ]));

        $this->dokumen->simpan($dokumen, 'pencatatan-bm.bast');

        return back()->with('success', 'BAST dibuat.')->with('dokumen_id', $dokumen->id);
    }

    public function pdf(Request $request, Dokumen $dokumen): Response
    {
        $this->pastikanSekolahSendiri($request, $dokumen->sekolah_id);

        return $this->dokumen->unduh($dokumen);
    }

    public function index(Request $request): InertiaResponse
    {
        $sekolahId = $this->resolveSekolahId($request);

        return Inertia::render('PencatatanBm/Dokumen/Index', [
            'dokumen' => Dokumen::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->latest('created_at')
                ->limit(100)
                ->get(['id', 'jenis', 'nomor', 'tanggal', 'created_at']),
            // Cetak langsung tidak menyimpan dokumen; butuh daftar ruang untuk KIR
            // dan daftar penanggung jawab yang benar-benar memegang aset di ruangan
            // itu (acuan: kartu_inventaris_ruangan.php memfilter penyaluran per lokasi).
            'pjPerRuang' => InventarisUnit::query()
                ->with('pegawai:id,nama_lengkap')
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->whereNotNull('ruang_id')
                ->whereNotNull('pegawai_id')
                ->whereIn('status', [InventarisUnit::STATUS_AKTIF, InventarisUnit::STATUS_DIKEMBALIKAN])
                ->get(['id', 'ruang_id', 'pegawai_id'])
                ->groupBy('ruang_id')
                ->map(fn (Collection $daftar): array => $daftar
                    ->map(fn (InventarisUnit $unit): array => [
                        'id' => (string) $unit->pegawai_id,
                        'nama' => (string) $unit->pegawai?->nama_lengkap,
                    ])
                    ->unique('id')
                    ->sortBy('nama')
                    ->values()
                    ->all())
                ->all(),
            'ruangList' => Ruang::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->orderBy('nama_ruang')
                ->get(['id', 'nama_ruang']),
        ]);
    }

    /** @return Builder<InventarisUnit> */
    private function unitScope(string $sekolahId): Builder
    {
        return InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap,nip'])
            ->where('sekolah_id', $sekolahId)
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->orderBy('kode_barang')
            ->orderBy('tahun_perolehan')
            ->orderBy('nomor_register');
    }

    private function sekolah(string $sekolahId): Sekolah
    {
        return Sekolah::findOrFail($sekolahId);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDasar(InventarisUnit $unit, Request $request, ?string $tanggal = null): array
    {
        $unit = $unit->loadMissing('pegawai');

        return [
            'sekolah' => $this->snapshotSekolah($unit->sekolah),
            'unit' => $this->qr->snapshotUnit($unit),
            'units' => [$this->qr->snapshotUnit($unit)],
            'penerima' => $this->snapshotPenerima($unit->pegawai),
            'nama_format' => NamaGelar::format($unit->pegawai?->nama_lengkap),
            'pejabat' => $this->dokumen->pejabat($unit->sekolah_id, $tanggal),
            'dicetak_oleh' => $request->user()->name,
        ];
    }

    /**
     * Pihak kedua dokumen: pegawai pemegang barang, atau placeholder bila unit
     * belum disalurkan.
     *
     * @return array<string, string|null>
     */
    private function snapshotPenerima(?Pegawai $pegawai): array
    {
        return [
            'nama' => $pegawai?->nama_lengkap,
            'nip' => $pegawai?->nip,
            'pangkat_golongan' => $pegawai?->pangkat_golongan,
            'jabatan' => $pegawai ? $this->snapshotPegawai($pegawai, now()->toDateString())['jabatan'] : null,
        ];
    }

    /**
     * Identitas pegawai + jabatan yang berlaku pada tanggal dokumen. Jabatan
     * diambil dari riwayat penugasan, bukan kolom master, supaya dokumen
     * bertanggal lampau tetap menyebut jabatan saat itu.
     *
     * @return array<string, mixed>
     */
    private function snapshotPegawai(Pegawai $pegawai, string $tanggal): array
    {
        $penugasan = app(PenugasanPegawaiService::class)->penugasanPegawaiPada(
            (string) $pegawai->sekolah_id,
            (string) $pegawai->id,
            $tanggal,
        );

        return [
            'pegawai_id' => $pegawai->id,
            'nama_lengkap' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
            'pangkat_golongan' => $pegawai->pangkat_golongan,
            'jabatan_id' => $penugasan?->jabatan_id,
            'jabatan' => $penugasan?->labelJabatan(),
            'jenis_penugasan' => $penugasan?->jenis_penugasan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotSekolah(?Sekolah $sekolah): array
    {
        return [
            'nama_sekolah' => $sekolah?->nama_sekolah,
            'npsn' => $sekolah?->npsn,
            'alamat' => $sekolah?->alamat,
            'telepon' => $sekolah?->telepon,
            'email' => $sekolah?->email,
            'website' => $sekolah?->website,
            'kota_kab' => $sekolah?->kota_kab,
            'kode_pos' => $sekolah?->kode_pos,
        ];
    }
}
