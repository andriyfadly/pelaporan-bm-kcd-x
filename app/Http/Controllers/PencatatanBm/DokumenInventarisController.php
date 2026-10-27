<?php

namespace App\Http\Controllers\PencatatanBm;

use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Concerns\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Services\Master\PenugasanPegawaiService;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\QrLabelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DokumenInventarisController extends Controller
{
    use ResolvesSekolah, TenantScope;

    public function __construct(
        private readonly DokumenInventarisService $dokumen,
        private readonly QrLabelService $qr,
    ) {}

    public function kir(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $ruang = Ruang::where('sekolah_id', $sekolahId)->findOrFail($request->input('ruang'));

        $units = $this->unitScope($sekolahId)->where('ruang_id', $ruang->id)->get();

        return $this->dokumen->kirRuang($ruang, $units);
    }

    public function bukuInventaris(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $tahun = (int) ($request->input('tahun') ?: now()->year);

        return $this->dokumen->bukuInventaris(
            $this->sekolah($sekolahId),
            $this->unitScope($sekolahId)->where('tahun_perolehan', $tahun)->get(),
            $tahun,
        );
    }

    public function sensus(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahIdWajib($request);
        $tahun = (int) ($request->input('tahun') ?: now()->year);

        return $this->dokumen->sensus(
            $this->sekolah($sekolahId),
            $this->unitScope($sekolahId)->where('tahun_perolehan', $tahun)->get(),
            $tahun,
        );
    }

    public function bast(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        $validated = $request->validate([
            'nomor' => ['nullable', 'string', 'max:150'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $dokumen = Dokumen::create([
            'sekolah_id' => $unit->sekolah_id,
            'jenis' => 'bast',
            'nomor' => $validated['nomor'] ?? null,
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'inventaris_unit_id' => $unit->id,
            'payload' => $this->payloadDasar($unit->fresh(), $request, $validated['tanggal'] ?? null),
            'user_id' => $request->user()->id,
        ]);

        $this->dokumen->simpan($dokumen, 'pencatatan-bm.dokumen');

        return back()->with('success', 'BAST dibuat.');
    }

    public function faktaIntegritas(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $pegawai->sekolah_id);
        $validated = $request->validate(['tanggal' => ['nullable', 'date']]);

        $units = $this->unitScope($pegawai->sekolah_id)->where('pegawai_id', $pegawai->id)->get();
        $tanggal = $validated['tanggal'] ?? now()->toDateString();

        $dokumen = Dokumen::create([
            'sekolah_id' => $pegawai->sekolah_id,
            'jenis' => 'fakta_integritas',
            'tanggal' => $tanggal,
            'inventaris_pegawai_id' => $pegawai->id,
            'payload' => [
                'sekolah' => $this->snapshotSekolah($pegawai->sekolah),
                'pegawai' => $this->snapshotPegawai($pegawai, $tanggal),
                'units' => $units->map(fn (InventarisUnit $unit) => $this->qr->snapshotUnit($unit))->all(),
                'pejabat' => $this->dokumen->pejabat($pegawai->sekolah_id, $tanggal),
                'dicetak_oleh' => $request->user()->name,
            ],
            'user_id' => $request->user()->id,
        ]);

        $this->dokumen->simpan($dokumen, 'pencatatan-bm.dokumen');

        return back()->with('success', 'Fakta integritas dibuat.');
    }

    public function suratPengembalian(Request $request, InventarisUnit $unit): RedirectResponse
    {
        $this->pastikanSekolahSendiri($request, $unit->sekolah_id);
        $validated = $request->validate(['tanggal' => ['nullable', 'date']]);
        $tanggal = $validated['tanggal'] ?? now()->toDateString();

        $mutasi = Mutasi::where('inventaris_unit_id', $unit->id)
            ->where('jenis', 'pengembalian')
            ->latest('tanggal')
            ->latest('created_at')
            ->first();

        if (! $mutasi) {
            throw ValidationException::withMessages([
                'unit' => 'Unit ini belum pernah dikembalikan, surat permohonan belum bisa dibuat.',
            ]);
        }

        $dokumen = Dokumen::create([
            'sekolah_id' => $unit->sekolah_id,
            'jenis' => 'surat_pengembalian',
            'tanggal' => $tanggal,
            'inventaris_unit_id' => $unit->id,
            'payload' => $this->payloadDasar($unit->fresh(), $request, $tanggal) + [
                'mutasi' => $this->qr->snapshotMutasi($mutasi),
            ],
            'user_id' => $request->user()->id,
        ]);

        $this->dokumen->simpan($dokumen, 'pencatatan-bm.dokumen');

        return back()->with('success', 'Surat permohonan pengembalian dibuat.');
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
            // Cetak langsung tidak menyimpan dokumen; butuh daftar ruang untuk KIR.
            'ruangList' => Ruang::query()
                ->when($sekolahId, fn (Builder $q) => $q->where('sekolah_id', $sekolahId))
                ->orderBy('nama_ruang')
                ->get(['id', 'nama_ruang']),
        ]);
    }

    private function unitScope(string $sekolahId): Builder
    {
        return InventarisUnit::query()
            ->with(['ruang:id,nama_ruang', 'pegawai:id,nama_lengkap,nip'])
            ->where('sekolah_id', $sekolahId)
            ->where('status', InventarisUnit::STATUS_AKTIF)
            ->orderBy('kode_barang')
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
        return [
            'sekolah' => $this->snapshotSekolah($unit->sekolah),
            'unit' => $this->qr->snapshotUnit($unit),
            'pejabat' => $this->dokumen->pejabat($unit->sekolah_id, $tanggal),
            'dicetak_oleh' => $request->user()->name,
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
        ];
    }
}
