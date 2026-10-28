<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\LaporanService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpRekonsiliasiBulananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Rekap'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function user(Sekolah $sekolah, string $username): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function barang(string $nama, string $sumberDana = 'BOS'): Barang
    {
        return Barang::create([
            'kode_barang' => '1.1.7.31.'.strtolower(str_replace(' ', '', $nama)),
            'nama_barang' => $nama,
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);
    }

    private function terima(Barang $barang, int $jumlah, string $tanggal, Sekolah $sekolah, bool $saldoAwal = false, ?int $periode = null): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $sekolah->id,
            'sumber_dana' => $barang->sumber_dana,
            'is_saldo_awal' => $saldoAwal,
            'periode_tahun' => $saldoAwal ? $periode : null,
            'tanggal_bast' => $saldoAwal ? null : $tanggal,
        ]);

        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 10000,
            'nilai_total' => $jumlah * 10000,
        ]);
    }

    private function setujui(Sekolah $sekolah, User $user, Barang $barang, int $jumlah, string $tanggal): void
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => $barang->sumber_dana,
            'status' => 'approved',
            'tanggal' => $tanggal,
        ]);

        $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => $jumlah,
            'jumlah_disetujui' => $jumlah,
        ]);
    }

    public function test_rekonsiliasi_bulanan_menyambung_saldo_antar_bulan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.rekap');
        $barang = $this->barang('Semen');

        // Saldo awal 2026 dibuckit ke 1 Januari, bukan dihitung sebagai mutasi Januari.
        $this->terima($barang, 100, '2026-01-01', $sekolah, true, 2026);
        $this->terima($barang, 50, '2026-03-10', $sekolah);
        $this->setujui($sekolah, $user, $barang, 30, '2026-02-05');
        $this->setujui($sekolah, $user, $barang, 20, '2026-12-20');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id);

        $this->assertSame([], $rekap['anomali']);
        $this->assertCount(1, $rekap['barang']);

        $row = $rekap['barang'][0];
        $this->assertSame(100, $row['saldo_awal']);

        // Januari: hanya saldo awal, tidak ada mutasi.
        $this->assertSame(['buka' => 100, 'masuk' => 0, 'keluar' => 0, 'tutup' => 100], $row['bulan']['1']);
        $this->assertSame(['buka' => 100, 'masuk' => 0, 'keluar' => 30, 'tutup' => 70], $row['bulan']['2']);
        $this->assertSame(['buka' => 70, 'masuk' => 50, 'keluar' => 0, 'tutup' => 120], $row['bulan']['3']);
        $this->assertSame(['buka' => 120, 'masuk' => 0, 'keluar' => 20, 'tutup' => 100], $row['bulan']['12']);
        $this->assertSame(100, $row['saldo_akhir']);

        // Rantai saldo: tutup bulan N = buka bulan N+1.
        for ($bulan = 1; $bulan < 12; $bulan++) {
            $this->assertSame(
                $row['bulan'][(string) $bulan]['tutup'],
                $row['bulan'][(string) ($bulan + 1)]['buka'],
            );
        }
    }

    public function test_rekonsiliasi_menandai_anomali_saldo_negatif(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.negatif');
        $barang = $this->barang('Paku');

        $this->terima($barang, 10, '2026-05-01', $sekolah);
        // Data anomali: keluar melebihi fisik (mis. hasil migrasi/manual).
        $this->setujui($sekolah, $user, $barang, 25, '2026-06-01');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id);

        $this->assertCount(1, $rekap['anomali']);
        $this->assertStringContainsString('Paku', $rekap['anomali'][0]);
        $this->assertSame(-15, $rekap['barang'][0]['bulan']['6']['tutup']);
    }

    public function test_rekonsiliasi_terpisah_per_sumber_dana_dan_sekolah(): void
    {
        $sekolah = $this->sekolah();
        $sekolahLain = $this->sekolah('SMKN Lain');
        $user = $this->user($sekolah, 'op.scope');
        $userLain = $this->user($sekolahLain, 'op.scope2');

        $bos = $this->barang('BOS Only', 'BOS');
        $bopd = $this->barang('BOPD Only', 'BOPD');

        $this->terima($bos, 10, '2026-01-15', $sekolah);
        $this->terima($bopd, 99, '2026-01-15', $sekolah);
        $this->terima($bos, 77, '2026-01-15', $sekolahLain);
        $this->setujui($sekolahLain, $userLain, $bos, 5, '2026-02-01');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id);

        $this->assertCount(1, $rekap['barang']);
        $this->assertSame('BOS Only', $rekap['barang'][0]['nama_barang']);
        // Penerimaan & pengajuan sekolah lain tidak ikut.
        $this->assertSame(10, $rekap['barang'][0]['bulan']['1']['masuk']);
        $this->assertSame(10, $rekap['barang'][0]['saldo_akhir']);
    }

    public function test_filter_rentang_bulan_memotong_kolom_tapi_saldo_tetap_berkesinambungan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.rentang');
        $barang = $this->barang('Semen');

        $this->terima($barang, 100, '2026-01-01', $sekolah, true, 2026);
        $this->setujui($sekolah, $user, $barang, 30, '2026-02-05');
        $this->terima($barang, 50, '2026-03-10', $sekolah);
        $this->setujui($sekolah, $user, $barang, 20, '2026-06-15');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id, 3, 6);

        $row = $rekap['barang'][0];

        // Hanya kolom 3..6 yang dikirim.
        $this->assertSame([3, 4, 5, 6], array_map('intval', array_keys($row['bulan'])));

        // Saldo awal = tutup Februari (Januari & Februari sudah ikut dihitung).
        $this->assertSame(70, $row['saldo_awal']);
        $this->assertSame(70, $row['bulan']['3']['buka']);
        $this->assertSame(120, $row['bulan']['3']['tutup']);
        $this->assertSame(100, $row['saldo_akhir']);
        $this->assertSame(3, $rekap['bulan_awal']);
        $this->assertSame(6, $rekap['bulan_akhir']);
    }

    public function test_filter_satu_bulan_menghasilkan_satu_kolom(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.satubulan');
        $barang = $this->barang('Kertas');

        $this->terima($barang, 40, '2026-01-10', $sekolah);
        $this->setujui($sekolah, $user, $barang, 10, '2026-03-02');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id, 3, 3);

        $row = $rekap['barang'][0];
        $this->assertSame(['3'], array_map('strval', array_keys($row['bulan'])));
        $this->assertSame(40, $row['saldo_awal']);
        $this->assertSame(30, $row['saldo_akhir']);
        $this->assertSame(30, $row['bulan']['3']['tutup']);
    }

    public function test_http_menolak_bulan_awal_melebihi_bulan_akhir(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.validasibulan');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.bos.laporan.index', ['bulan_awal' => 6, 'bulan_akhir' => 2]))
            ->assertSessionHasErrors('bulan_awal');
    }

    public function test_http_meneruskan_rentang_bulan_ke_halaman(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.httprentang');
        $barang = $this->barang('Semen');
        $this->terima($barang, 25, '2026-03-01', $sekolah);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.bos.laporan.index', ['tahun' => 2026, 'bulan_awal' => 3, 'bulan_akhir' => 4]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/Index')
                ->where('bulanAwal', 3)
                ->where('bulanAkhir', 4)
                ->where('rekonsiliasi.bulan_awal', 3)
                ->where('rekonsiliasi.bulan_akhir', 4));
    }

    public function test_filter_rentang_melaporkan_saldo_buka_negatif_dari_bulan_sebelumnya(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.bukanegatif');
        $barang = $this->barang('Paku');

        $this->terima($barang, 10, '2026-01-05', $sekolah);
        // Anomali terjadi di Januari, di luar rentang yang diminta.
        $this->setujui($sekolah, $user, $barang, 25, '2026-01-20');

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id, 3, 4);

        $this->assertCount(1, $rekap['anomali']);
        $this->assertStringContainsString('saldo buka 03 sudah negatif', $rekap['anomali'][0]);
        $this->assertSame(-15, $rekap['barang'][0]['saldo_awal']);
    }

    public function test_saldo_awal_tiap_tahun_dijumlahkan_sebagai_stok(): void
    {
        $sekolah = $this->sekolah();
        $barang = $this->barang('Semen');

        $this->terima($barang, 100, '2025-01-01', $sekolah, true, 2025);
        $this->terima($barang, 120, '2026-01-01', $sekolah, true, 2026);

        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id);

        // Saldo awal tiap tahun adalah penambahan stok tersendiri, konsisten
        // dengan StokService::fisik().
        $this->assertSame(220, $rekap['barang'][0]['saldo_awal']);
        $this->assertSame(220, $rekap['barang'][0]['saldo_akhir']);
        $this->assertSame([], $rekap['anomali']);
    }

    public function test_rentang_tidak_wajar_di_clamp_di_service(): void
    {
        $sekolah = $this->sekolah();
        $barang = $this->barang('Semen');
        $this->terima($barang, 5, '2026-01-05', $sekolah);

        // Pemanggil langsung (bukan HTTP) tidak boleh menyebabkan undefined index.
        $rekap = app(LaporanService::class)->rekonsiliasiBulanan('BOS', 2026, $sekolah->id, 0, 99);

        $this->assertSame(1, $rekap['bulan_awal']);
        $this->assertSame(12, $rekap['bulan_akhir']);
        $this->assertCount(12, $rekap['barang'][0]['bulan']);
    }
}
