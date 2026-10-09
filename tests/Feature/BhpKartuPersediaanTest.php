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

class BhpKartuPersediaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Kartu'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function user(Sekolah $sekolah, string $username, string $role = 'operator_sekolah'): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function barang(string $nama, string $sumberDana = 'BOS'): Barang
    {
        return Barang::create([
            'kode_barang' => '1.1.7.30.'.strtolower(str_replace(' ', '', $nama)),
            'nama_barang' => $nama,
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);
    }

    /**
     * Catat penerimaan (masuk) pada tanggal tertentu. Ini penerimaan biasa
     * (bukan saldo awal), sehingga boleh beberapa kali pada periode yang sama.
     */
    private function terima(Barang $barang, int $jumlah, string $tanggal, Sekolah $sekolah): PenerimaanDetail
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $sekolah->id,
            'sumber_dana' => $barang->sumber_dana,
            'is_saldo_awal' => false,
            'tanggal_bast' => $tanggal,
        ]);

        return PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 10000,
            'nilai_total' => $jumlah * 10000,
        ]);
    }

    private function pengajuan(Sekolah $sekolah, User $user, Barang $barang, int $jumlah, string $tanggal, string $status = 'approved', ?int $disetujui = null): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => $barang->sumber_dana,
            'status' => $status,
            'tanggal' => $tanggal,
        ]);

        $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => $jumlah,
            'jumlah_disetujui' => $status === 'approved' ? ($disetujui ?? $jumlah) : null,
        ]);

        return $pengajuan;
    }

    public function test_kartu_persediaan_menghitung_saldo_berjalan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartu');
        $barang = $this->barang('Semen');

        $this->terima($barang, 100, '2026-01-05', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 30, '2026-02-10');
        $this->terima($barang, 50, '2026-03-01', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 20, '2026-04-15');

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');

        $this->assertSame('Semen', $kartu['barang']['nama_barang']);
        // 4 mutasi: 2 masuk, 2 keluar, urut kronologis
        $this->assertCount(4, $kartu['mutasi']);

        $saldo = array_column($kartu['mutasi'], 'saldo');
        $this->assertSame([100, 70, 120, 100], $saldo);

        $masuk = array_column($kartu['mutasi'], 'masuk');
        $this->assertSame([100, 0, 50, 0], $masuk);
        $keluar = array_column($kartu['mutasi'], 'keluar');
        $this->assertSame([0, 30, 0, 20], $keluar);

        $this->assertSame(100, $kartu['barang']['saldo_akhir']);
        $this->assertSame(150, $kartu['barang']['total_masuk']);
        $this->assertSame(50, $kartu['barang']['total_keluar']);
    }

    public function test_kartu_persediaan_urut_kronologis_masuk_lebih_dulu(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.urut');
        $barang = $this->barang('Kertas');

        // Keluar dicatat pada tanggal yang sama dengan masuk -> masuk harus lebih dulu.
        $this->terima($barang, 40, '2026-05-01', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 10, '2026-05-01');

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');
        $jenis = array_column($kartu['mutasi'], 'jenis');

        $this->assertSame(['masuk', 'keluar'], $jenis);
        $this->assertSame([40, 30], array_column($kartu['mutasi'], 'saldo'));
    }

    public function test_kartu_persediaan_mengabaikan_pengajuan_belum_disetujui(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.draft');
        $barang = $this->barang('Pulpen');

        $this->terima($barang, 60, '2026-06-01', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 25, '2026-06-05', 'submitted');
        $this->pengajuan($sekolah, $user, $barang, 15, '2026-06-06', 'draft');

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');

        $this->assertCount(1, $kartu['mutasi']);
        $this->assertSame(60, $kartu['barang']['saldo_akhir']);
        $this->assertSame(0, $kartu['barang']['total_keluar']);
    }

    public function test_kartu_persediaan_menggunakan_jumlah_disetujui_bukan_diminta(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.koreksi');
        $barang = $this->barang('Kayu');

        $this->terima($barang, 100, '2026-07-01', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 40, '2026-07-03', 'approved', 15);

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');

        $this->assertSame(15, $kartu['mutasi'][1]['keluar']);
        $this->assertSame(85, $kartu['barang']['saldo_akhir']);
    }

    public function test_kartu_persediaan_menolak_barang_sumber_dana_lain(): void
    {
        $barang = $this->barang('BOPD Only', 'BOPD');

        $this->expectException(\RuntimeException::class);

        app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');
    }

    public function test_operator_dapat_membuka_halaman_kartu_persediaan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartuhttp');
        $barang = $this->barang('Semen');
        $this->terima($barang, 10, '2026-08-01', $sekolah);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.kartu-persediaan', ['barang_id' => $barang->id, 'sumber_dana' => 'BOS']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Laporan/KartuPersediaan')
                ->where('kartu.barang.saldo_akhir', 10));
    }

    public function test_user_tanpa_akses_kartu_persediaan_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create([
            'name' => 'Tanpa Role',
            'username' => 'norole.kartu',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.kartu-persediaan'))
            ->assertForbidden();
    }

    public function test_operator_dapat_export_kartu_persediaan_excel(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartuxls');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, '2026-09-01', $sekolah);
        $this->pengajuan($sekolah, $user, $barang, 10, '2026-09-02');

        $response = $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', [
                'sumber_dana' => 'BOS',
                'jenis' => 'kartu_persediaan',
                'barang_id' => $barang->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_export_kartu_persediaan_wajib_barang_id(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartuvalid');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', ['sumber_dana' => 'BOS', 'jenis' => 'kartu_persediaan']))
            ->assertSessionHasErrors('barang_id');
    }

    public function test_barang_id_tidak_valid_ditolak_sebagai_validation_error(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartubad');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.kartu-persediaan', ['barang_id' => 'bukan-uuid', 'sumber_dana' => 'BOS']))
            ->assertSessionHasErrors('barang_id');
    }

    public function test_barang_id_tidak_dikenal_ditolak_sebagai_validation_error(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartuunknown');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.kartu-persediaan', ['barang_id' => '00000000-0000-0000-0000-000000000000', 'sumber_dana' => 'BOS']))
            ->assertSessionHasErrors('barang_id');
    }

    public function test_barang_sumber_dana_lain_pada_export_tidak_menghasilkan_error_server(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.kartumismatch');
        $barangBopd = $this->barang('Barang Mismatch', 'BOPD');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', [
                'sumber_dana' => 'BOS',
                'jenis' => 'kartu_persediaan',
                'barang_id' => $barangBopd->id,
            ]))
            ->assertSessionHasErrors('barang_id');
    }
}
