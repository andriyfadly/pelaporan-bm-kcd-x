<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpKartuPersediaanExport;
use App\Exports\DistribusiBhp\BhpPengeluaranExport;
use App\Exports\DistribusiBhp\BhpStokExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\SettingsService;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly LaporanService $laporan,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            ...$this->aturanPeriode(),
        ]);
        $sumberDana = SumberDana::normalisasi($validated['sumber_dana'] ?? SumberDana::BOS);
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        return Inertia::render('DistribusiBhp/Laporan/Index', [
            'sumberDana' => $sumberDana,
            'periode' => [
                'tahun' => $periode->tahun,
                'bulan' => $periode->bulan,
                'label' => $periode->label(),
            ],
            'stok' => $this->laporan->laporanStok($sumberDana, $sekolahId, $periode),
            'pengeluaran' => $this->laporan->bukuPengeluaran($sumberDana, $sekolahId, $periode),
        ]);
    }

    public function kartuPersediaan(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::normalisasi($request->input('sumber_dana', SumberDana::BOS));

        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            'barang_id' => ['nullable', 'uuid', SumberDana::existsRule('barang', $sumberDana)],
            ...$this->aturanPeriode(),
        ]);
        $barangId = (string) ($validated['barang_id'] ?? '');
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        return SumberDana::dengan($sumberDana, fn (): Response => Inertia::render('DistribusiBhp/Laporan/KartuPersediaan', [
            'sumberDana' => $sumberDana,
            'barangId' => $barangId,
            'periode' => [
                'tahun' => $periode->tahun,
                'bulan' => $periode->bulan,
                'label' => $periode->label(),
            ],
            'kartu' => $barangId !== '' ? $this->laporan->kartuPersediaan($barangId, $sumberDana, $sekolahId, $periode) : null,
            'daftarBarang' => Barang::orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang']),
        ]));
    }

    /** Aturan validasi periode yang dipakai semua halaman & export laporan. */
    private function aturanPeriode(): array
    {
        return [
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    private function daftarDokumen(Request $request, string $sumberDana, string $jenis): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $label = self::LABEL[$jenis] ?? $jenis;

        return SumberDana::dengan($sumberDana, function () use ($sekolahId, $jenis, $label, $sumberDana): Response {
            $items = Dokumen::query()
                ->with([
                    'pengajuan:id,tanggal,nomor_pengajuan,divisi_id,sekolah_id',
                    'pengajuan.dokumen:id,bhp_pengajuan_id,jenis,nomor',
                ])
                ->where('jenis', $jenis)
                ->when($sekolahId !== null, fn ($q) => $q->whereHas('pengajuan', fn ($w) => $w->where('sekolah_id', $sekolahId)))
                ->orderByDesc('tanggal_titimangsa')
                ->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Dokumen $d) => [
                    'id' => $d->id,
                    'nomor' => $d->nomor,
                    'tanggal' => $d->tanggal_titimangsa?->toDateString(),
                    'pengajuan' => $d->pengajuan ? [
                        'id' => $d->pengajuan->id,
                        // Nomor pengajuan tidak pernah diisi; nomor Nota
                        // Permintaan adalah identitas praktisnya.
                        'nomor' => $d->pengajuan->nomor_pengajuan
                            ?: $d->pengajuan->dokumen->firstWhere('jenis', 'nota_permintaan')?->nomor,
                        'tanggal' => $d->pengajuan->tanggal?->toDateString(),
                        'sekolah_id' => $d->pengajuan->sekolah_id,
                    ] : null,
                ]);

            return Inertia::render('DistribusiBhp/Laporan/DokumenList', [
                'jenis' => $jenis,
                'jenisLabel' => $label,
                'sumberDana' => $sumberDana,
                'items' => $items,
            ]);
        });
    }

    private const LABEL = [
        'bast' => 'Berita Acara Serah Terima',
        'bap' => 'Berita Acara Pemeriksaan',
        'buku_penyaluran' => 'Buku Penyaluran Barang',
        'stok_opname' => 'Berita Acara Stok Opname',
        'surat_permintaan' => 'Surat Permintaan Barang',
        'surat_penyaluran' => 'Surat Perintah Penyaluran Barang',
    ];

    public function bastInternal(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'bast');
    }

    public function bap(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'bap');
    }

    public function bukuPengeluaranPage(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS));
        $validated = $request->validate($this->aturanPeriode());
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        return Inertia::render('DistribusiBhp/Laporan/BukuPengeluaran', [
            'jenisLabel' => 'Buku Pengeluaran Barang',
            'sumberDana' => $sumberDana,
            'periode' => [
                'tahun' => $periode->tahun,
                'bulan' => $periode->bulan,
                'label' => $periode->label(),
            ],
            'items' => $this->laporan->bukuPengeluaran($sumberDana, $sekolahId, $periode),
        ]);
    }

    public function bukuPenyaluran(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'buku_penyaluran');
    }

    public function stokOpname(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'stok_opname');
    }

    public function suratPermintaan(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'surat_permintaan');
    }

    public function suratPenyaluran(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'surat_penyaluran');
    }

    public function bastSerahTerima(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::normalisasi((string) $request->input('sumber_dana', SumberDana::BOS)), 'bast');
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            'jenis' => ['required', Rule::in(['stok', 'pengeluaran', 'kartu_persediaan'])],
            'barang_id' => ['required_if:jenis,kartu_persediaan', 'uuid'],
            ...$this->aturanPeriode(),
        ]);

        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::normalisasi(
            $validated['sumber_dana']
                ?? SumberDana::aktif()
                ?? SumberDana::BOS,
        );
        $jenis = $validated['jenis'];
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        if ($jenis === 'kartu_persediaan') {
            validator(
                ['barang_id' => $validated['barang_id'] ?? null],
                ['barang_id' => ['required', 'uuid', SumberDana::existsRule('barang', $sumberDana)]],
            )->validate();
        }

        $suffixPeriode = $periode->kode();

        if ($jenis === 'stok') {
            $rows = $this->laporan->laporanStok($sumberDana, $sekolahId, $periode);
            $export = new BhpStokExport($rows);
            $filename = "laporan_stok_bhp_{$sumberDana}_{$suffixPeriode}.xlsx";
        } elseif ($jenis === 'kartu_persediaan') {
            try {
                $kartu = $this->laporan->kartuPersediaan($validated['barang_id'], $sumberDana, $sekolahId, $periode);
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
            $rows = $kartu['mutasi'];
            $export = new BhpKartuPersediaanExport($kartu['barang'], $rows);
            $filename = "kartu_persediaan_bhp_{$sumberDana}_{$suffixPeriode}.xlsx";
        } else {
            $rows = $this->laporan->bukuPengeluaran($sumberDana, $sekolahId, $periode);
            $export = new BhpPengeluaranExport($rows);
            $filename = "buku_pengeluaran_bhp_{$sumberDana}_{$suffixPeriode}.xlsx";
        }

        activity('sistem')
            ->event('unduh-laporan-bhp')
            ->withProperties([
                'ringkasan' => "Unduh {$filename} (".count($rows).' baris)',
                'sumber_dana' => $sumberDana,
                'jenis' => $jenis,
                'periode' => $suffixPeriode,
                'jumlah' => count($rows),
            ])
            ->log('unduh-laporan-bhp');

        return Excel::download($export, $filename);
    }
}
