<?php

namespace App\Http\Controllers\DistribusiBhp;

use App\Exports\DistribusiBhp\BhpKartuPersediaanExport;
use App\Exports\DistribusiBhp\BhpPenerimaanExport;
use App\Exports\DistribusiBhp\BhpPengeluaranExport;
use App\Exports\DistribusiBhp\BhpStokExport;
use App\Http\Controllers\Concerns\ResolvesSekolah;
use App\Http\Controllers\Controller;
use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\Master\Sekolah;
use App\Services\DistribusiBhp\LaporanService;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LaporanController extends Controller
{
    use ResolvesSekolah;

    public function __construct(
        private readonly LaporanService $laporan,
    ) {}

    public function index(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            ...$this->aturanPeriode(),
            'bulan_awal' => ['nullable', 'integer', 'between:1,12'],
            'bulan_akhir' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $sumberDana = SumberDana::pilih($validated['sumber_dana'] ?? null);
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        $bulanAwal = (int) ($validated['bulan_awal'] ?? 1);
        $bulanAkhir = (int) ($validated['bulan_akhir'] ?? 12);

        if ($bulanAwal > $bulanAkhir) {
            throw ValidationException::withMessages([
                'bulan_awal' => 'Bulan awal tidak boleh melebihi bulan akhir.',
            ]);
        }

        return Inertia::render('DistribusiBhp/Laporan/Index', [
            'sumberDana' => $sumberDana,
            'periode' => [
                'tahun' => $periode->tahun,
                'bulan' => $periode->bulan,
                'label' => $periode->label(),
            ],
            'bulanAwal' => $bulanAwal,
            'bulanAkhir' => $bulanAkhir,
            'stok' => $this->laporan->laporanStok($sumberDana, $sekolahId, $periode),
            'pengeluaran' => $this->laporan->bukuPengeluaran($sumberDana, $sekolahId, $periode),
            'rekonsiliasi' => $this->laporan->rekonsiliasiBulanan($sumberDana, $periode->tahun, $sekolahId, $bulanAwal, $bulanAkhir),
        ]);
    }

    public function kartuPersediaan(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::pilih($request->input('sumber_dana'));

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
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'bast');
    }

    public function bap(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'bap');
    }

    public function bukuPengeluaranPage(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::pilih($request->input('sumber_dana'));
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

    public function bukuPenerimaanPage(Request $request): Response
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::pilih($request->input('sumber_dana'));
        $validated = $request->validate($this->aturanPeriode());
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        return Inertia::render('DistribusiBhp/Laporan/BukuPenerimaan', [
            'jenisLabel' => 'Buku Penerimaan Barang',
            'sumberDana' => $sumberDana,
            'periode' => [
                'tahun' => $periode->tahun,
                'bulan' => $periode->bulan,
                'label' => $periode->label(),
            ],
            'items' => $this->laporan->bukuPenerimaan($sumberDana, $sekolahId, $periode),
        ]);
    }

    /** Cetak Buku Penerimaan (PDF) dengan isi yang sama seperti halaman & Excel. */
    public function cetakBukuPenerimaan(Request $request): HttpResponse
    {
        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::pilih($request->input('sumber_dana'));
        $validated = $request->validate($this->aturanPeriode());
        $periode = Periode::dari(
            isset($validated['tahun']) ? (int) $validated['tahun'] : null,
            isset($validated['bulan']) ? (int) $validated['bulan'] : null,
        );

        $sekolah = $sekolahId ? Sekolah::find($sekolahId) : null;

        $pdf = Pdf::loadView('distribusi-bhp.buku-penerimaan', [
            'sumberDana' => $sumberDana,
            'periode' => $periode,
            'items' => $this->laporan->bukuPenerimaan($sumberDana, $sekolahId, $periode),
            'sekolah' => $sekolah->nama_sekolah ?? 'Semua Sekolah',
            'alamat' => $sekolah->alamat ?? '',
            'telepon' => $sekolah->telepon ?? '',
            'website' => $sekolah->website ?? '',
            'email' => $sekolah->email ?? '',
            'kodePos' => $sekolah->kode_pos ?? '',
        ])->setPaper('a4', 'landscape');

        return $pdf->download("buku_penerimaan_bhp_{$sumberDana}_{$periode->kode()}.pdf");
    }

    public function bukuPenyaluran(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'buku_penyaluran');
    }

    public function stokOpname(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'stok_opname');
    }

    public function suratPermintaan(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'surat_permintaan');
    }

    public function suratPenyaluran(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'surat_penyaluran');
    }

    public function bastSerahTerima(Request $request): Response
    {
        return $this->daftarDokumen($request, SumberDana::pilih($request->input('sumber_dana')), 'bast');
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'sumber_dana' => ['nullable', Rule::in(SumberDana::SEMUA)],
            'jenis' => ['required', Rule::in(['stok', 'penerimaan', 'pengeluaran', 'kartu_persediaan'])],
            'barang_id' => ['required_if:jenis,kartu_persediaan', 'uuid'],
            ...$this->aturanPeriode(),
        ]);

        $sekolahId = $this->resolveSekolahId($request);
        $sumberDana = SumberDana::pilih($validated['sumber_dana'] ?? null);
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
        } elseif ($jenis === 'penerimaan') {
            $rows = $this->laporan->bukuPenerimaan($sumberDana, $sekolahId, $periode);
            $export = new BhpPenerimaanExport($rows);
            $filename = "buku_penerimaan_bhp_{$sumberDana}_{$suffixPeriode}.xlsx";
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
