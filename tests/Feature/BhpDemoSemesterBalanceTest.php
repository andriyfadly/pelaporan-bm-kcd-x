<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Models\Master\Sekolah;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\StokService;
use App\Support\DistribusiBhp\Periode;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\DemoDistribusiBhpSmkn4KuninganSeeder;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Balance semester 1 2026 atas dataset demo SMK Negeri 4 Kuningan.
 *
 * Ekspektasi dihitung langsung dari baris transaksi (bukan dari service yang
 * sedang diuji), sehingga service yang salah tidak bisa "membenarkan" dirinya
 * sendiri.
 */
class BhpDemoSemesterBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

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
    }

    /**
     * Mutasi mentah per barang per bulan, langsung dari tabel detail.
     *
     * @return array{masuk:array<string,array<int,int>>,keluar:array<string,array<int,int>>}
     */
    private function mutasiMentah(string $dana): array
    {
        return SumberDana::dengan($dana, function (): array {
            $masuk = [];
            $keluar = [];

            PenerimaanDetail::query()
                ->with('penerimaan:id,tanggal_bast,is_saldo_awal,periode_tahun,sekolah_id')
                ->whereHas('penerimaan', fn ($q) => $q->where('sekolah_id', $this->sekolah->id))
                ->get()
                ->each(function (PenerimaanDetail $detail) use (&$masuk): void {
                    $penerimaan = $detail->penerimaan;
                    $bulan = $penerimaan->is_saldo_awal
                        ? 0
                        : (int) $penerimaan->tanggal_bast?->format('n');

                    $masuk[(string) $detail->barang_id][$bulan] =
                        ($masuk[(string) $detail->barang_id][$bulan] ?? 0) + (int) $detail->jumlah;
                });

            PengajuanDetail::query()
                ->with('pengajuan:id,tanggal,status,sekolah_id')
                ->whereHas('pengajuan', fn ($q) => $q
                    ->where('sekolah_id', $this->sekolah->id)
                    ->where('status', 'approved'))
                ->get()
                ->each(function (PengajuanDetail $detail) use (&$keluar): void {
                    $bulan = (int) $detail->pengajuan->tanggal?->format('n');

                    $keluar[(string) $detail->barang_id][$bulan] =
                        ($keluar[(string) $detail->barang_id][$bulan] ?? 0) + (int) $detail->jumlah_disetujui;
                });

            return ['masuk' => $masuk, 'keluar' => $keluar];
        });
    }

    public function test_rekonsiliasi_bulanan_seimbang_dengan_mutasi_mentah(): void
    {
        foreach (SumberDana::SEMUA as $dana) {
            $mutasi = $this->mutasiMentah($dana);
            $rekap = app(LaporanService::class)->rekonsiliasiBulanan($dana, 2026, $this->sekolah->id, 1, 6);

            $this->assertSame([], $rekap['anomali'], "Anomali {$dana} harus kosong.");

            foreach ($rekap['barang'] as $row) {
                $id = (string) $row['barang_id'];
                $saldo = $mutasi['masuk'][$id][0] ?? 0;

                $this->assertSame(
                    $saldo,
                    $row['saldo_awal'],
                    "Saldo awal {$dana} {$row['nama_barang']} harus sama dengan saldo awal 2026.",
                );

                for ($bulan = 1; $bulan <= 6; $bulan++) {
                    $masuk = $mutasi['masuk'][$id][$bulan] ?? 0;
                    $keluar = $mutasi['keluar'][$id][$bulan] ?? 0;
                    $sel = $row['bulan'][(string) $bulan];

                    $this->assertSame($saldo, $sel['buka'], "Buka bulan {$bulan} {$dana} {$row['nama_barang']}.");
                    $this->assertSame($masuk, $sel['masuk'], "Masuk bulan {$bulan} {$dana} {$row['nama_barang']}.");
                    $this->assertSame($keluar, $sel['keluar'], "Keluar bulan {$bulan} {$dana} {$row['nama_barang']}.");

                    $saldo += $masuk - $keluar;

                    $this->assertSame($saldo, $sel['tutup'], "Tutup bulan {$bulan} {$dana} {$row['nama_barang']}.");
                    $this->assertGreaterThanOrEqual(0, $sel['tutup'], "Stok {$dana} {$row['nama_barang']} tidak boleh negatif.");
                }

                $this->assertSame($saldo, $row['saldo_akhir'], "Saldo akhir Juni {$dana} {$row['nama_barang']}.");
            }
        }
    }

    public function test_stok_akhir_semester_sama_dengan_fisik_dikurangi_terpakai(): void
    {
        $stok = app(StokService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $akhir = app(LaporanService::class)->rekonsiliasiBulanan($dana, 2026, $this->sekolah->id, 1, 6);

            foreach ($akhir['barang'] as $row) {
                $barang = SumberDana::dengan($dana, fn (): Barang => Barang::findOrFail($row['barang_id']))
                    ->untukSumberDana($dana);

                $fisikNyata = $stok->fisik($barang, $this->sekolah->id)
                    - $stok->terpakai($barang, $this->sekolah->id);

                $this->assertSame(
                    $row['saldo_akhir'],
                    $fisikNyata,
                    "Stok {$dana} {$row['nama_barang']}: rekonsiliasi dan StokService harus sama.",
                );
            }
        }
    }

    public function test_laporan_stok_periode_juni_sama_dengan_saldo_akhir_rekonsiliasi(): void
    {
        $laporan = app(LaporanService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $rekonsiliasi = collect($laporan->rekonsiliasiBulanan($dana, 2026, $this->sekolah->id, 1, 6)['barang'])
                ->keyBy('barang_id');
            $stok = collect($laporan->laporanStok($dana, $this->sekolah->id, Periode::bulan(2026, 6)))
                ->keyBy('barang_id');

            $this->assertCount(10, $stok, "Laporan stok {$dana} harus memuat 10 barang.");

            foreach ($stok as $id => $row) {
                $this->assertSame(
                    $rekonsiliasi[$id]['saldo_akhir'],
                    $row['fisik_nyata'],
                    "Laporan stok {$dana} {$row['nama_barang']} harus sama dengan rekonsiliasi.",
                );
                $this->assertSame($row['fisik'] - $row['terpakai'], $row['fisik_nyata']);
                $this->assertSame($row['fisik_nyata'] * $row['harga_satuan'], $row['nilai_total']);
            }
        }
    }

    public function test_kartu_persediaan_menutup_di_saldo_yang_sama(): void
    {
        $laporan = app(LaporanService::class);
        $periode = Periode::bulan(2026, 6);

        foreach (SumberDana::SEMUA as $dana) {
            $daftar = SumberDana::dengan($dana, fn () => Barang::orderBy('nama_barang')->pluck('id')->all());

            foreach ($daftar as $barangId) {
                $kartu = $laporan->kartuPersediaan($barangId, $dana, $this->sekolah->id, $periode);

                $this->assertSame(
                    $kartu['barang']['saldo_awal'] + $kartu['barang']['total_masuk'] - $kartu['barang']['total_keluar'],
                    $kartu['barang']['saldo_akhir'],
                    "Kartu {$dana} {$kartu['barang']['nama_barang']} tidak balance.",
                );

                $terakhir = end($kartu['mutasi']);
                $this->assertSame(
                    $kartu['barang']['saldo_akhir'],
                    $terakhir === false ? $kartu['barang']['saldo_awal'] : $terakhir['saldo'],
                    "Saldo berjalan terakhir kartu {$dana} harus sama dengan saldo akhir.",
                );
            }
        }
    }

    public function test_pengajuan_submitted_dan_rejected_tidak_masuk_pengeluaran(): void
    {
        $laporan = app(LaporanService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $pengeluaran = collect($laporan->bukuPengeluaran($dana, $this->sekolah->id, Periode::bulan(2026, 6)));

            $idTerlarang = SumberDana::dengan($dana, fn () => Pengajuan::where('sekolah_id', $this->sekolah->id)
                ->whereIn('status', ['submitted', 'rejected'])
                ->pluck('id')
                ->all());

            $this->assertNotEmpty($idTerlarang, "Dataset {$dana} harus punya pengajuan submitted/rejected.");
            $this->assertEmpty(
                $pengeluaran->whereIn('pengajuan_id', $idTerlarang)->all(),
                "Pengajuan submitted/rejected tidak boleh muncul di buku pengeluaran {$dana}.",
            );
        }
    }

    /**
     * Pengajuan Juni yang memuat dokumen tambahan (BAST/stok opname dst).
     * Itu juga pengajuan yang disetujui sebagian.
     */
    private function pengajuanJuni(string $dana): Pengajuan
    {
        $pengajuan = SumberDana::dengan($dana, fn (): Pengajuan => Pengajuan::where('sekolah_id', $this->sekolah->id)
            ->whereHas('dokumen', fn ($q) => $q->where('jenis', 'stok_opname'))
            ->firstOrFail());

        return $pengajuan->untukSumberDana($dana)->load('details.barang');
    }

    public function test_approval_sebagian_memakai_jumlah_disetujui(): void
    {
        [$dana] = SumberDana::SEMUA;

        $sebagian = $this->pengajuanJuni($dana);
        $detail = $sebagian->details->firstWhere(fn ($d): bool => (int) $d->jumlah_disetujui < (int) $d->jumlah_diminta);

        $this->assertNotNull($detail, 'Harus ada minimal satu detail yang disetujui sebagian.');

        $this->assertLessThan(
            (int) $detail->jumlah_diminta,
            (int) $detail->jumlah_disetujui,
            'Skenario approval sebagian harus benar-benar lebih kecil dari permintaan.',
        );

        $buku = collect(app(LaporanService::class)->bukuPengeluaran($dana, $this->sekolah->id, Periode::bulan(2026, 6)))
            ->where('pengajuan_id', $sebagian->id)
            ->keyBy('nama_barang');

        $nama = SumberDana::dengan($dana, fn (): ?string => Barang::find($detail->barang_id)?->nama_barang);

        $this->assertArrayHasKey($nama, $buku->all(), 'Barang approval sebagian harus ada di buku pengeluaran.');
        $this->assertSame((int) $detail->jumlah_disetujui, $buku[$nama]['jumlah']);
        $this->assertSame(
            (int) $detail->jumlah_disetujui * (int) $detail->harga_satuan,
            $buku[$nama]['nilai_total'],
            'Nilai pengeluaran harus memakai jumlah disetujui x harga snapshot.',
        );
    }

    /**
     * Buku penerimaan dan buku pengeluaran harus menutup ke saldo akhir.
     */
    public function test_buku_penerimaan_dan_pengeluaran_menutup_ke_saldo_akhir(): void
    {
        $laporan = app(LaporanService::class);

        foreach (SumberDana::SEMUA as $dana) {
            $masuk = collect();
            for ($bulan = 1; $bulan <= 6; $bulan++) {
                $masuk = $masuk->merge(
                    collect($laporan->bukuPenerimaan($dana, $this->sekolah->id, Periode::bulan(2026, $bulan)))
                        // Saldo awal muncul di Januari tetapi bukan pembelian
                        // bulan itu; ia sudah dihitung sebagai saldo awal.
                        ->reject(fn (array $row): bool => $row['is_saldo_awal'])
                );
            }

            $keluar = collect();
            for ($bulan = 1; $bulan <= 6; $bulan++) {
                $keluar = $keluar->merge($laporan->bukuPengeluaran($dana, $this->sekolah->id, Periode::bulan(2026, $bulan)));
            }

            $saldoAwal = collect($laporan->bukuPenerimaan($dana, $this->sekolah->id, null))
                ->where('is_saldo_awal', true)
                ->sum('jumlah');

            $rekonsiliasi = collect($laporan->rekonsiliasiBulanan($dana, 2026, $this->sekolah->id, 1, 6)['barang']);

            $this->assertSame(
                (int) $rekonsiliasi->sum('saldo_awal'),
                (int) $saldoAwal,
                "Saldo awal buku penerimaan {$dana} harus sama dengan saldo awal rekonsiliasi.",
            );

            $this->assertSame(
                (int) $rekonsiliasi->sum('saldo_akhir'),
                (int) $saldoAwal + (int) $masuk->sum('jumlah') - (int) $keluar->sum('jumlah'),
                "Buku penerimaan - buku pengeluaran {$dana} harus menutup ke saldo akhir.",
            );
        }
    }

    public function test_buku_penerimaan_periode_tidak_memuat_bulan_lain(): void
    {
        $juni = collect(app(LaporanService::class)->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 6)));

        $this->assertNotEmpty($juni);
        $this->assertTrue(
            $juni->every(fn (array $row): bool => str_starts_with((string) $row['tanggal'], '2026-06')),
            'Buku penerimaan Juni hanya boleh memuat transaksi Juni.',
        );
        $this->assertFalse(
            $juni->contains(fn (array $row): bool => $row['is_saldo_awal']),
            'Saldo awal tidak boleh muncul sebagai penerimaan bulan berjalan.',
        );
    }

    public function test_buku_penerimaan_bos_dan_bopd_tidak_tercampur(): void
    {
        $bos = collect(app(LaporanService::class)->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 6)));
        $bopd = collect(app(LaporanService::class)->bukuPenerimaan('BOPD', $this->sekolah->id, Periode::bulan(2026, 6)));

        $this->assertNotSame($bos->sum('jumlah'), $bopd->sum('jumlah'));
        $this->assertTrue($bos->every(fn (array $row): bool => str_contains((string) $row['no_bast'], '/BOS/')));
        $this->assertTrue($bopd->every(fn (array $row): bool => str_contains((string) $row['no_bast'], '/BOPD/')));
    }
}
