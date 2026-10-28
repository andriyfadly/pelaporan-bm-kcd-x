<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\LaporanService;
use App\Support\DistribusiBhp\Periode;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BhpBukuPenerimaanTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 BUKU TERIMA',
            'kota_kab' => 'KOTA BANDUNG',
            'npsn' => '11112222',
        ]);

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.buku.terima',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');
    }

    private function barang(string $nama, int $harga): Barang
    {
        $barang = Barang::create([
            'kode_barang' => 'K-'.$nama,
            'nama_barang' => $nama,
            'satuan' => 'Pcs',
        ]);

        $barang->harga()->create(['harga_satuan' => $harga]);

        return $barang;
    }

    private function terima(string $tanggal, array $items, bool $saldoAwal = false, ?string $tanggalPembayaran = null): Penerimaan
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'no_bast' => $saldoAwal ? null : 'BAST/'.$tanggal,
            'tanggal_bast' => $saldoAwal ? null : $tanggal,
            'no_nota_invoice' => 'INV/'.$tanggal,
            'no_kwitansi' => $tanggalPembayaran === null ? null : 'KW/'.$tanggal,
            'tanggal_pembayaran' => $tanggalPembayaran,
            'is_saldo_awal' => $saldoAwal,
            'periode_tahun' => $saldoAwal ? 2026 : null,
        ]);

        foreach ($items as [$barang, $jumlah, $harga]) {
            $penerimaan->details()->create([
                'barang_id' => $barang->id,
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'nilai_total' => $jumlah * $harga,
            ]);
        }

        return $penerimaan;
    }

    private function data(): void
    {
        $kertas = $this->barang('Kertas', 50000);
        $pulpen = $this->barang('Pulpen', 3000);

        $this->terima('2026-05-01', [[$kertas, 100, 50000], [$pulpen, 200, 3000]], true);
        $this->terima('2026-05-10', [[$kertas, 20, 52000]]);
        $this->terima('2026-06-05', [[$kertas, 10, 55000], [$pulpen, 50, 3500]]);
    }

    public function test_buku_penerimaan_hanya_memuat_periode_terpilih(): void
    {
        $this->data();

        $juni = app(LaporanService::class)->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 6));

        $this->assertCount(2, $juni);
        $this->assertSame(10, $juni[0]['jumlah']);
        $this->assertSame(55000, $juni[0]['harga_satuan']);
        $this->assertSame(550000, $juni[0]['nilai_total']);
        $this->assertSame('2026-06-05', $juni[0]['tanggal']);
        $this->assertSame('BAST/2026-06-05', $juni[0]['no_bast']);
    }

    public function test_buku_penerimaan_tanpa_periode_memuat_saldo_awal(): void
    {
        $this->data();

        $semua = collect(app(LaporanService::class)->bukuPenerimaan('BOS', $this->sekolah->id, null));

        // 2 detail saldo awal + 1 detail Mei + 2 detail Juni.
        $this->assertCount(5, $semua);
        $this->assertSame(2, $semua->where('is_saldo_awal', true)->count());
        $this->assertSame('Saldo Awal 2026', $semua->firstWhere('is_saldo_awal', true)['no_bast']);
        $this->assertSame(300, (int) $semua->where('is_saldo_awal', true)->sum('jumlah'));
    }

    public function test_buku_penerimaan_terisolasi_per_sekolah(): void
    {
        $this->data();

        $lain = Sekolah::create(['nama_sekolah' => 'SMKN LAIN', 'kota_kab' => 'BANDUNG']);
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $lain->id,
            'no_bast' => 'BAST/LAIN',
            'tanggal_bast' => '2026-06-06',
        ]);
        $penerimaan->details()->create([
            'barang_id' => Barang::first()->id,
            'jumlah' => 999,
            'harga_satuan' => 1000,
            'nilai_total' => 999000,
        ]);

        $juni = app(LaporanService::class)->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 6));

        $this->assertSame(60, (int) collect($juni)->sum('jumlah'));
        $this->assertNotContains(999, collect($juni)->pluck('jumlah')->all());
    }

    /**
     * "Lunas Bayar" = barang sudah diterima tetapi transaksi keuangan belum
     * terjadi sampai akhir bulan laporan. Dibayar pada bulan yang sama berarti
     * keterangan kosong; dibayar bulan berikutnya tetap bertanda pada bulan
     * penerimaan, dan pembayarannya tidak memindahkan baris ke bulan itu.
     */
    public function test_keterangan_lunas_bayar_mengikuti_bulan_pembayaran(): void
    {
        $kertas = $this->barang('Kertas', 50000);

        // Januari: dibayar di bulan yang sama.
        $this->terima('2026-01-10', [[$kertas, 5, 50000]], false, '2026-01-20');
        // Februari: baru dibayar Maret.
        $this->terima('2026-02-10', [[$kertas, 7, 50000]], false, '2026-03-05');
        // Maret: belum dibayar.
        $this->terima('2026-03-10', [[$kertas, 9, 50000]]);

        $laporan = app(LaporanService::class);

        $januari = $laporan->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 1));
        $this->assertFalse($januari[0]['lunas_bayar'], 'Dibayar pada Januari: keterangan harus kosong.');

        $februari = $laporan->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 2));
        $this->assertTrue($februari[0]['lunas_bayar'], 'Dibayar Maret: Februari masih Lunas Bayar.');

        $maret = $laporan->bukuPenerimaan('BOS', $this->sekolah->id, Periode::bulan(2026, 3));
        $this->assertCount(1, $maret, 'Transaksi Februari tidak boleh berpindah ke Maret.');
        $this->assertSame('2026-03-10', $maret[0]['tanggal']);
        $this->assertTrue($maret[0]['lunas_bayar'], 'Belum dibayar: Maret bertanda Lunas Bayar.');
    }

    public function test_operator_dapat_membuka_halaman_buku_penerimaan(): void
    {
        $this->data();

        $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.buku-penerimaan', ['tahun' => 2026, 'bulan' => 6]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/BukuPenerimaan')
                ->where('sumberDana', 'BOS')
                ->has('items', 2));
    }

    public function test_operator_dapat_export_excel_buku_penerimaan(): void
    {
        $this->data();

        $response = $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.export', ['jenis' => 'penerimaan', 'tahun' => 2026, 'bulan' => 6]));

        $response->assertOk();
        $this->assertStringContainsString('buku_penerimaan_bhp_BOS_2026-06.xlsx', (string) $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'buku-terima').'.xlsx';
        file_put_contents($path, $response->streamedContent() ?: $response->getContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // Heading + 2 baris Juni; saldo awal Mei tidak boleh ikut.
        $this->assertCount(3, $rows);
        $this->assertSame(['Tanggal', 'No. BAST', 'No. Nota/Invoice', 'Penyedia', 'Kode Barang', 'Nama Barang', 'Satuan', 'Jumlah', 'Harga Satuan', 'Nilai Total', 'Keterangan'], $rows[0]);
        $this->assertSame('2026-06-05', $rows[1][0]);
        $this->assertSame(10, (int) $rows[1][7]);
        $this->assertSame(550000, (int) $rows[1][9]);

        @unlink($path);
    }

    public function test_operator_dapat_mencetak_pdf_buku_penerimaan(): void
    {
        $this->data();

        $response = $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.buku-penerimaan.cetak', ['tahun' => 2026, 'bulan' => 6]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('buku_penerimaan_bhp_BOS_2026-06.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_user_tanpa_permission_cetak_tidak_dapat_pdf_buku_penerimaan(): void
    {
        $this->data();

        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bend.buku.terima',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->get(route('distribusi-bhp.bos.laporan.buku-penerimaan.cetak', ['tahun' => 2026, 'bulan' => 6]))
            ->assertForbidden();
    }

    public function test_buku_penerimaan_menolak_sekolah_lain(): void
    {
        $this->data();

        $lain = Sekolah::create(['nama_sekolah' => 'SMKN LAIN 2', 'kota_kab' => 'BANDUNG']);
        $userLain = User::create([
            'name' => 'Operator Lain',
            'username' => 'op.lain.terima',
            'password' => bcrypt('password'),
            'sekolah_id' => $lain->id,
            'password_changed_at' => now(),
        ]);
        $userLain->assignRole('operator_sekolah');

        $this->actingAs($userLain)
            ->get(route('distribusi-bhp.bos.laporan.buku-penerimaan', ['tahun' => 2026, 'bulan' => 6]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 0));
    }

    /** Skill csv-excel-export: pre-flight supaya tidak ada unduhan kosong tanpa peringatan. */
    public function test_export_tetap_menghasilkan_heading_saat_kosong(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('distribusi-bhp.bos.laporan.export', ['jenis' => 'penerimaan', 'tahun' => 2026, 'bulan' => 3]));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'kosong').'.xlsx';
        file_put_contents($path, $response->streamedContent() ?: $response->getContent());

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $this->assertCount(1, $rows, 'File kosong tetap harus memuat baris heading.');
        $this->assertSame('Tanggal', $rows[0][0]);

        @unlink($path);
    }
}
