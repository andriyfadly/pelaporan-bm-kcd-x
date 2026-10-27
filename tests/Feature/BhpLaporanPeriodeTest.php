<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\LaporanService;
use App\Support\DistribusiBhp\Periode;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan BHP per periode: batas bulan tidak bocor, saldo awal kartu
 * persediaan konsisten dengan laporan stok, dan export memakai periode yang
 * dipilih (bukan seluruh histori).
 */
class BhpLaporanPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $user;

    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Periode', 'kota_kab' => 'Kota Bandung']);
        $this->user = User::create([
            'name' => 'Operator',
            'username' => 'op.periode',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->user->assignRole('operator_sekolah');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.001',
            'nama_barang' => 'Semen',
            'satuan' => 'Sak',
            'sumber_dana' => 'BOS',
        ]);
    }

    private function terima(int $jumlah, string $tanggal): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => false,
            'tanggal_bast' => $tanggal,
        ]);

        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 10_000,
            'nilai_total' => $jumlah * 10_000,
        ]);
    }

    private function keluar(int $jumlah, string $status, string $tanggal): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->user->id,
            'sumber_dana' => 'BOS',
            'status' => $status,
            'tanggal' => $tanggal,
        ]);

        $pengajuan->details()->create([
            'barang_id' => $this->barang->id,
            'jumlah_diminta' => $jumlah,
            'jumlah_disetujui' => $status === 'approved' ? $jumlah : null,
            'jumlah_direservasi' => $status === 'submitted' ? $jumlah : 0,
            'status_reservasi' => $status === 'submitted' ? 'direservasi' : 'tidak_direservasi',
        ]);

        return $pengajuan;
    }

    public function test_laporan_stok_tidak_memakai_transaksi_setelah_akhir_periode(): void
    {
        $this->terima(100, '2026-07-05');
        $this->keluar(30, 'approved', '2026-07-10');
        $this->terima(50, '2026-08-01');
        $this->keluar(20, 'approved', '2026-09-02');
        $this->keluar(10, 'submitted', '2026-08-15');

        $juli = app(LaporanService::class)->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 7));

        $this->assertSame(100, $juli[0]['fisik']);
        $this->assertSame(30, $juli[0]['terpakai']);
        $this->assertSame(0, $juli[0]['reservasi'], 'Reservasi Agustus tidak boleh mengurangi stok Juli.');
        $this->assertSame(70, $juli[0]['tersedia']);
    }

    public function test_laporan_stok_menghitung_reservasi_hanya_sampai_akhir_periode(): void
    {
        $this->terima(100, '2026-06-01');
        $this->keluar(25, 'submitted', '2026-06-20');
        $this->keluar(15, 'submitted', '2026-07-20');

        $juni = app(LaporanService::class)->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 6));

        $this->assertSame(25, $juni[0]['reservasi']);
        $this->assertSame(75, $juni[0]['tersedia']);
    }

    public function test_saldo_awal_masuk_sejak_awal_tahun_periode(): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 80,
            'harga_satuan' => 10_000,
            'nilai_total' => 800_000,
        ]);

        $januari = app(LaporanService::class)->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 1));
        $dulu = app(LaporanService::class)->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2025, 12));

        $this->assertSame(80, $januari[0]['fisik'], 'Saldo awal 2026 harus ada di Januari 2026.');
        $this->assertSame(0, $dulu[0]['fisik'], 'Saldo awal 2026 tidak boleh muncul di 2025.');
    }

    public function test_terima_tanpa_tanggal_bast_memakai_titimangsa(): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => false,
            'tanggal_titimangsa' => '2026-07-15',
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 25,
            'harga_satuan' => 10_000,
            'nilai_total' => 250_000,
        ]);

        $laporan = app(LaporanService::class);

        $this->assertSame(0, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 6))[0]['fisik']);
        $this->assertSame(25, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 7))[0]['fisik']);
    }

    public function test_saldo_awal_tahun_lalu_muncul_di_tahun_periodenya(): void
    {
        // Saldo awal 2025 bertanggal BAST Januari 2025: Januari 2026 harus
        // memuatnya sebagai posisi awal tahun, Desember 2025 juga.
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'periode_tahun' => 2025,
            'tanggal_bast' => '2025-01-02',
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 40,
            'harga_satuan' => 10_000,
            'nilai_total' => 400_000,
        ]);

        $laporan = app(LaporanService::class);

        $this->assertSame(0, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2024, 12))[0]['fisik']);
        $this->assertSame(40, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2025, 12))[0]['fisik']);
        $this->assertSame(40, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 1))[0]['fisik']);
    }

    public function test_saldo_awal_tanpa_periode_mengikuti_tanggalnya(): void
    {
        // Data lama: saldo awal tanpa periode_tahun tetap harus muncul pada
        // laporan setelah tanggalnya, bukan hilang selamanya.
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'tanggal_bast' => '2026-06-10',
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 15,
            'harga_satuan' => 10_000,
            'nilai_total' => 150_000,
        ]);

        $laporan = app(LaporanService::class);

        $this->assertSame(0, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 5))[0]['fisik']);
        $this->assertSame(15, $laporan->laporanStok('BOS', $this->sekolah->id, Periode::bulan(2026, 6))[0]['fisik']);
    }

    public function test_buku_pengeluaran_hanya_memuat_bulan_terpilih(): void
    {
        $this->keluar(10, 'approved', '2026-07-31');
        $this->keluar(20, 'approved', '2026-08-01');
        $this->keluar(99, 'draft', '2026-07-15');

        $juli = app(LaporanService::class)->bukuPengeluaran('BOS', $this->sekolah->id, Periode::bulan(2026, 7));

        $this->assertCount(1, $juli);
        $this->assertSame(10, $juli[0]['jumlah']);
        $this->assertSame('2026-07-31', $juli[0]['tanggal']);
    }

    public function test_kartu_persediaan_periode_memakai_saldo_awal_dan_saldo_akhir_benar(): void
    {
        $this->terima(100, '2026-06-10');
        $this->keluar(20, 'approved', '2026-06-15');
        $this->terima(50, '2026-07-05');
        $this->keluar(30, 'approved', '2026-07-20');

        $kartu = app(LaporanService::class)->kartuPersediaan(
            $this->barang->id,
            'BOS',
            $this->sekolah->id,
            Periode::bulan(2026, 7),
        );

        $this->assertSame(80, $kartu['barang']['saldo_awal'], 'Saldo awal Juli = 100 - 20.');
        $this->assertSame(50, $kartu['barang']['total_masuk']);
        $this->assertSame(30, $kartu['barang']['total_keluar']);
        $this->assertSame(100, $kartu['barang']['saldo_akhir']);
        $this->assertCount(2, $kartu['mutasi'], 'Hanya mutasi Juli yang tampil.');
        $this->assertSame([130, 100], array_column($kartu['mutasi'], 'saldo'));
    }

    public function test_saldo_akhir_kartu_sama_dengan_laporan_stok_periode(): void
    {
        $this->terima(100, '2026-06-10');
        $this->keluar(40, 'approved', '2026-07-02');
        $this->keluar(25, 'submitted', '2026-07-09');

        $periode = Periode::bulan(2026, 7);
        $kartu = app(LaporanService::class)->kartuPersediaan($this->barang->id, 'BOS', $this->sekolah->id, $periode);
        $stok = app(LaporanService::class)->laporanStok('BOS', $this->sekolah->id, $periode);

        // Kartu hanya menghitung mutasi approved (60 = fisik - approved),
        // laporan stok mengurangi reservasi aktif (35).
        $this->assertSame(60, $kartu['barang']['saldo_akhir']);
        $this->assertSame($stok[0]['fisik'] - $stok[0]['terpakai'], $kartu['barang']['saldo_akhir']);
        $this->assertSame(35, $stok[0]['tersedia']);
    }

    public function test_halaman_laporan_menampilkan_periode_terpilih(): void
    {
        $this->terima(100, '2026-07-05');
        $this->keluar(40, 'approved', '2026-08-05');

        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.bos.laporan.index', ['tahun' => 2026, 'bulan' => 7]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/Index')
                ->where('periode.tahun', 2026)
                ->where('periode.bulan', 7)
                ->where('periode.label', 'Juli 2026')
                ->where('stok.0.terpakai', 0)
                ->where('pengeluaran', []));
    }

    public function test_halaman_laporan_default_bulan_berjalan(): void
    {
        CarbonImmutable::setTestNow('2026-10-27 10:00:00');

        try {
            $this->actingAs($this->user)
                ->get(route('distribusi-bhp.bos.laporan.index'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('periode.tahun', 2026)
                    ->where('periode.bulan', 10));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_export_stok_memakai_nama_berkas_berperiode(): void
    {
        $this->terima(10, '2026-07-01');

        $response = $this->actingAs($this->user)->get(route('distribusi-bhp.bos.laporan.export', [
            'jenis' => 'stok',
            'tahun' => 2026,
            'bulan' => 7,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('2026-07', $response->headers->get('content-disposition') ?? '');
    }

    public function test_export_kartu_persediaan_memakai_periode(): void
    {
        $this->terima(100, '2026-06-10');
        $this->keluar(20, 'approved', '2026-06-15');

        $response = $this->actingAs($this->user)->get(route('distribusi-bhp.bos.laporan.export', [
            'jenis' => 'kartu_persediaan',
            'barang_id' => $this->barang->id,
            'tahun' => 2026,
            'bulan' => 7,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('2026-07', $response->headers->get('content-disposition') ?? '');
    }

    public function test_export_menolak_bulan_di_luar_rentang(): void
    {
        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.bos.laporan.export', ['jenis' => 'stok', 'bulan' => 13]))
            ->assertSessionHasErrors('bulan');
    }

    public function test_halaman_buku_pengeluaran_memuat_periode(): void
    {
        $this->keluar(12, 'approved', '2026-07-04');
        $this->keluar(8, 'approved', '2026-08-04');

        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.bos.laporan.buku-pengeluaran', ['tahun' => 2026, 'bulan' => 7]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/BukuPengeluaran')
                ->where('periode.label', 'Juli 2026')
                ->has('items', 1)
                ->where('items.0.jumlah', 12));
    }

    public function test_halaman_kartu_persediaan_memuat_periode(): void
    {
        $this->terima(100, '2026-06-10');
        $this->keluar(20, 'approved', '2026-06-15');

        $this->actingAs($this->user)
            ->get(route('distribusi-bhp.bos.laporan.kartu-persediaan', [
                'barang_id' => $this->barang->id,
                'tahun' => 2026,
                'bulan' => 7,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/KartuPersediaan')
                ->where('periode.label', 'Juli 2026')
                ->where('kartu.barang.saldo_awal', 80)
                ->where('kartu.mutasi', []));
    }
}
