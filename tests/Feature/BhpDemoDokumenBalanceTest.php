<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\SettingsService;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
use App\Support\HariKerja;
use Database\Seeders\DemoDistribusiBhpSmkn4KuninganSeeder;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hasil cetak harus memuat angka yang sama dengan transaksi sumbernya.
 * Rendering Blade dipakai untuk isi, endpoint PDF dipakai untuk kontrak HTTP.
 */
class BhpDemoDokumenBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create([
            'nama_sekolah' => 'SMK NEGERI 4 KUNINGAN',
            'npsn' => DemoDistribusiBhpSmkn4KuninganSeeder::NPSN,
            'kota_kab' => 'KUNINGAN',
        ]);

        $this->seed(DemoDistribusiBhpSmkn4KuninganSeeder::class);

        $this->operator = User::where('username', 'demo-operator-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN)->firstOrFail();
    }

    /**
     * Pengajuan Juni yang memiliki seluruh dokumen (wajib + tambahan). Dokumen
     * tambahan sengaja dibuat pada satu pengajuan bulan terakhir.
     */
    private function pengajuanJuni(string $dana): Pengajuan
    {
        $pengajuan = SumberDana::dengan($dana, fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->where('nomor_pengajuan', 'like', '%06-%')
            ->whereHas('dokumen', fn ($q) => $q
                ->where('jenis', 'stok_opname')
                ->whereYear('tanggal_titimangsa', 2026)
                ->whereMonth('tanggal_titimangsa', 6))
            ->firstOrFail());

        return $pengajuan->untukSumberDana($dana)->load('details.barang', 'divisi', 'pemohon', 'dokumen');
    }

    private function renderDokumen(Pengajuan $pengajuan, string $jenis, string $view): string
    {
        $dokumen = $pengajuan->dokumen->firstWhere('jenis', $jenis);
        $this->assertNotNull($dokumen, "Dokumen {$jenis} harus ada pada pengajuan demo.");

        $stok = app(StokService::class)->rekapPerBarang(
            $pengajuan->details->pluck('barang')->filter()->unique('id'),
            (string) $pengajuan->sekolah_id,
            $dokumen->tanggal_titimangsa?->toDateString(),
        );

        foreach ($pengajuan->details as $detail) {
            $id = (string) $detail->barang_id;
            if (isset($stok[$id])) {
                $stok[$id]['tersedia'] += (int) $detail->jumlah_disetujui;
            }
        }

        return view($view, [
            'judul' => ucwords(str_replace('_', ' ', $jenis)),
            'dokumen' => $dokumen,
            'pengajuan' => $pengajuan,
            'stokPerBarang' => $stok,
            'sekolah' => $this->sekolah->nama_sekolah,
            'alamat' => '',
            'telepon' => '',
            'website' => '',
            'email' => '',
            'kodePos' => '',
            'pemohon' => $pengajuan->pemohon?->name ?? '-',
            'penandatangan' => $dokumen->payload['penandatangan'] ?? [],
            'labelJabatan' => app(PenugasanPegawaiService::class)->labelPenandatangan((string) $this->sekolah->id),
            'settings' => app(SettingsService::class)->get($pengajuan->sumber_dana),
        ])->render();
    }

    private function teks(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    public function test_nota_permintaan_memuat_divisi_dan_jumlah_diminta(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $pengajuan = $this->pengajuanJuni($dana);
            $teks = $this->teks($this->renderDokumen($pengajuan, 'nota_permintaan', 'distribusi-bhp.nota-permintaan'));

            $this->assertStringContainsString($this->sekolah->nama_sekolah, $teks, "Kop sekolah {$dana} harus tercetak.");
            $this->assertStringContainsString($pengajuan->divisi->nama, $teks, "Divisi peminta {$dana} harus tercetak.");

            foreach ($pengajuan->details as $detail) {
                $this->assertStringContainsString($detail->barang->nama_barang, $teks, "Barang {$dana} harus tercetak.");
                $this->assertStringContainsString(
                    (string) $detail->jumlah_diminta,
                    $teks,
                    "Jumlah diminta {$dana} ({$detail->barang->nama_barang}) harus tercetak.",
                );
            }

            $nomor = $pengajuan->dokumen->firstWhere('jenis', 'nota_permintaan')->nomor;
            $this->assertStringContainsString($nomor, $teks, "Nomor Nota Permintaan {$dana} harus tercetak.");
        }
    }

    public function test_spb_memuat_sisa_stok_bukan_nol(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $pengajuan = $this->pengajuanJuni($dana);
            $html = $this->renderDokumen($pengajuan, 'surat_permintaan', 'distribusi-bhp.surat-permintaan');

            $tersedia = app(StokService::class)->rekapPerBarang(
                $pengajuan->details->pluck('barang')->filter()->unique('id'),
                (string) $this->sekolah->id,
            );

            foreach ($pengajuan->details as $detail) {
                $sisa = (int) $tersedia[(string) $detail->barang_id]['tersedia'] + (int) $detail->jumlah_disetujui;

                $this->assertGreaterThan(0, $sisa, "Sisa stok {$dana} {$detail->barang->nama_barang} tidak boleh 0.");
                $this->assertStringContainsString(
                    '<td class="center">'.$sisa.'</td>',
                    $html,
                    "Kolom sisa barang {$dana} harus berisi {$sisa}, bukan 0.",
                );
                $this->assertStringContainsString((string) $detail->jumlah_diminta, $html);
            }
        }
    }

    public function test_sppb_hanya_mencetak_jumlah_disetujui(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $pengajuan = $this->pengajuanJuni($dana);
            $teks = $this->teks($this->renderDokumen($pengajuan, 'surat_penyaluran', 'distribusi-bhp.surat-penyaluran'));

            foreach ($pengajuan->details as $detail) {
                $this->assertStringContainsString(
                    (string) $detail->jumlah_disetujui,
                    $teks,
                    "SPPB {$dana} harus mencetak jumlah disetujui.",
                );
            }

            $sebagian = $pengajuan->details->first();
            $this->assertLessThan(
                (int) $sebagian->jumlah_diminta,
                (int) $sebagian->jumlah_disetujui,
                'Skenario approval sebagian harus memotong jumlah.',
            );
        }
    }

    public function test_stok_opname_satu_per_bulan_dan_cocok_saldo_akhir_bulan_itu(): void
    {
        $laporan = app(LaporanService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $daftar = SumberDana::dengan($dana, fn () => Dokumen::where('jenis', 'stok_opname')
                ->whereHas('pengajuan', fn ($q) => $q->where('sekolah_id', $this->sekolah->id))
                ->orderBy('tanggal_titimangsa')
                ->get());

            $this->assertCount(6, $daftar, "Stok opname {$dana} harus tepat satu per bulan Januari–Juni.");
            $this->assertSame(
                [1, 2, 3, 4, 5, 6],
                $daftar->map(fn (Dokumen $d): int => (int) $d->tanggal_titimangsa->format('n'))->all(),
                "Bulan stok opname {$dana} harus lengkap tanpa duplikat.",
            );

            foreach ($daftar as $dokumen) {
                $bulan = (int) $dokumen->tanggal_titimangsa->format('n');

                $this->assertTrue(
                    HariKerja::kerja($dokumen->tanggal_titimangsa),
                    "Stok opname {$dana} bulan {$bulan} harus bertanggal hari kerja ({$dokumen->nomor}).",
                );
                $this->assertSame(
                    HariKerja::kerjaTerakhir(2026, $bulan)->toDateString(),
                    $dokumen->tanggal_titimangsa->toDateString(),
                    "Stok opname {$dana} bulan {$bulan} harus memakai hari kerja terakhir bulan itu.",
                );

                $items = collect($dokumen->payload['items']);
                $akhir = collect($laporan->rekonsiliasiBulanan($dana, 2026, $this->sekolah->id, 1, $bulan)['barang']);

                $this->assertCount(10, $items, "Stok opname {$dana} bulan {$bulan} harus memuat 10 barang.");

                foreach ($items as $item) {
                    $baris = $akhir->firstWhere('barang_id', $item['barang_id']);

                    $this->assertNotNull($baris, "Barang {$item['nama_barang']} harus ada di rekonsiliasi {$dana}.");
                    $this->assertSame(
                        $baris['saldo_akhir'],
                        $item['fisik_nyata'],
                        "Stok opname {$dana} bulan {$bulan} {$item['nama_barang']} harus sama dengan saldo akhir bulan itu.",
                    );
                    $this->assertSame(
                        $item['fisik_nyata'] * $item['harga_satuan'],
                        $item['nilai_total'],
                        "Nilai stok opname {$dana} {$item['nama_barang']} harus kuantitas x harga.",
                    );
                }

                $penandatangan = $dokumen->payload['penandatangan'] ?? [];
                foreach (['kepala_sekolah', 'wakasek_sarpras', 'pembantu_pengurus_barang'] as $kode) {
                    $this->assertNotEmpty($penandatangan[$kode]['nama'] ?? null, "Penandatangan {$kode} pada stok opname {$dana} kosong.");
                }
            }
        }
    }

    public function test_stok_opname_kedua_pada_bulan_yang_sama_ditolak(): void
    {
        [$dana] = SumberDana::SEMUA;

        $pengajuan = SumberDana::dengan($dana, fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->where('status', 'approved')
            ->where('nomor_pengajuan', 'like', '%06-%')
            ->whereDoesntHave('dokumen', fn ($q) => $q->where('jenis', 'stok_opname'))
            ->firstOrFail());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah dibuat untuk sekolah ini');

        SumberDana::dengan($dana, fn () => app(DokumenService::class)
            ->generateTambahan($pengajuan, 'stok_opname', '2026-06-29'));
    }

    public function test_stok_opname_pada_hari_libur_ditolak_dengan_saran_hari_kerja(): void
    {
        [$dana] = SumberDana::SEMUA;

        $pengajuan = SumberDana::dengan($dana, fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->where('status', 'approved')
            ->where('nomor_pengajuan', 'like', '%06-%')
            ->whereDoesntHave('dokumen', fn ($q) => $q->where('jenis', 'stok_opname'))
            ->firstOrFail());

        // 5 Juli 2026 = Minggu, di luar dataset demo.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hari kerja terakhir: 31/07/2026');

        SumberDana::dengan($dana, fn () => app(DokumenService::class)
            ->generateTambahan($pengajuan, 'stok_opname', '2026-07-05'));
    }

    public function test_buku_pengeluaran_dokumen_sama_dengan_laporan_bulan_juni(): void
    {
        $laporan = app(LaporanService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $dokumen = SumberDana::dengan($dana, fn () => Dokumen::where('jenis', 'buku_pengeluaran')
                ->whereHas('pengajuan', fn ($q) => $q->where('sekolah_id', $this->sekolah->id))
                ->latest('id')
                ->firstOrFail());

            $laporanJuni = collect($laporan->bukuPengeluaran($dana, $this->sekolah->id, Periode::bulan(2026, 6)));
            $items = collect($dokumen->payload['items']);

            $this->assertSame($laporanJuni->count(), $items->count(), "Jumlah baris buku pengeluaran {$dana}.");
            $this->assertSame((int) $laporanJuni->sum('jumlah'), (int) $items->sum('jumlah'), "Total kuantitas {$dana}.");
            $this->assertSame((int) $laporanJuni->sum('nilai_total'), (int) $items->sum('nilai_total'), "Total nilai {$dana}.");
        }
    }

    public function test_buku_penyaluran_terhubung_ke_bast_dan_sppb(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $pengajuan = $this->pengajuanJuni($dana);
            $dokumen = SumberDana::dengan($dana, fn () => Dokumen::where('jenis', 'buku_penyaluran')
                ->where('bhp_pengajuan_id', $pengajuan->id)
                ->firstOrFail());

            $bast = $pengajuan->dokumen->firstWhere('jenis', 'bast');
            $sppb = $pengajuan->dokumen->firstWhere('jenis', 'surat_penyaluran');

            $items = collect($dokumen->payload['items']);

            // Identitas baris = nomor_pengajuan bila ada, kalau tidak nomor
            // Nota Permintaan (sama dengan aturan LaporanService).
            $nomorBaris = $pengajuan->nomor_pengajuan
                ?: $pengajuan->dokumen->firstWhere('jenis', 'nota_permintaan')?->nomor;

            $milikPengajuan = $items->where('nomor_dokumen', $nomorBaris);

            $this->assertNotEmpty($milikPengajuan, "Buku penyaluran {$dana} harus memuat transaksi pengajuan ini.");
            $this->assertSame($bast->nomor, $milikPengajuan->first()['bast_nomor'] ?? null);
            $this->assertSame($sppb->nomor, $milikPengajuan->first()['sppb_nomor'] ?? null);
            $this->assertSame($pengajuan->divisi->nama, $milikPengajuan->first()['penerima'] ?? null);
        }
    }

    public function test_pdf_dokumen_dapat_diunduh_dan_berisi(): void
    {
        $pengajuan = $this->pengajuanJuni(SumberDana::BOS);

        foreach (['nota_permintaan', 'surat_permintaan', 'surat_penyaluran', 'bast', 'bap', 'stok_opname', 'buku_pengeluaran', 'buku_penyaluran'] as $jenis) {
            $dokumen = $pengajuan->dokumen->firstWhere('jenis', $jenis);
            $this->assertNotNull($dokumen, "Dokumen {$jenis} harus ada.");

            $response = $this->actingAs($this->operator)
                ->get(route('distribusi-bhp.dokumen.pdf', [$pengajuan, $dokumen]));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), "PDF {$jenis} salah tipe.");
            $this->assertStringContainsString(
                str_replace('/', '-', $dokumen->nomor),
                (string) $response->headers->get('content-disposition'),
                "Nama berkas PDF {$jenis} harus memakai nomor dokumen.",
            );
            $this->assertGreaterThan(1000, strlen((string) $response->getContent()), "PDF {$jenis} kosong.");
        }
    }
}
