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

class BhpLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Laporan'): Sekolah
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

    private function terima(Barang $barang, int $jumlah, ?Sekolah $sekolah = null): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $sekolah?->id,
            'sumber_dana' => $barang->sumber_dana,
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 10000,
            'nilai_total' => $jumlah * 10000,
        ]);
    }

    public function test_laporan_stok_mengembalikan_empat_tier(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, $sekolah);

        // pengajuan submitted -> reservasi 30
        $submitted = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $submitted->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 30,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 30,
        ]);

        // pengajuan approved -> terpakai 20
        $approved = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $approved->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 20,
            'jumlah_disetujui' => 20,
        ]);

        $rows = app(LaporanService::class)->laporanStok('BOS');

        $this->assertCount(1, $rows);
        $this->assertSame(100, $rows[0]['fisik']);
        $this->assertSame(20, $rows[0]['terpakai']);
        $this->assertSame(30, $rows[0]['reservasi']);
        $this->assertSame(50, $rows[0]['tersedia']);
    }

    public function test_laporan_stok_terisolasi_per_sumber_dana(): void
    {
        $bos = $this->barang('Kertas', 'BOS');
        $bopd = $this->barang('Pulpen', 'BOPD');
        $this->terima($bos, 40);
        $this->terima($bopd, 10);

        $service = app(LaporanService::class);

        $this->assertSame(1, count($service->laporanStok('BOS')));
        $this->assertSame('Kertas', $service->laporanStok('BOS')[0]['nama_barang']);
        $this->assertSame('Pulpen', $service->laporanStok('BOPD')[0]['nama_barang']);
    }

    public function test_buku_pengeluaran_mencatat_item_approved(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap2');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, $sekolah);

        $approved = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $approved->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 25,
            'jumlah_disetujui' => 25,
        ]);

        $rows = app(LaporanService::class)->bukuPengeluaran('BOS');

        $this->assertCount(1, $rows);
        $this->assertSame('Semen', $rows[0]['nama_barang']);
        $this->assertSame(25, $rows[0]['jumlah']);
        $this->assertSame('2026-06-18', $rows[0]['tanggal']);
    }

    public function test_kartu_barang_menampilkan_mutasi_masuk_dan_keluar(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap3');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, $sekolah);

        $approved = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $approved->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 25,
            'jumlah_disetujui' => 25,
        ]);

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS');
        $jenis = array_column($kartu['mutasi'], 'jenis');

        $this->assertContains('masuk', $jenis);
        $this->assertContains('keluar', $jenis);
        // 100 masuk - 25 keluar (approved) = 75
        $this->assertSame(75, $kartu['barang']['saldo_akhir']);
    }

    public function test_operator_dapat_membuka_halaman_laporan_bhp(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap4');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.index'))
            ->assertOk();
    }

    public function test_user_tanpa_permission_laporan_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create([
            'name' => 'Tanpa Role',
            'username' => 'norole.lap',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.index'))
            ->assertForbidden();
    }

    public function test_operator_dapat_export_laporan_stok_excel(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap5');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, $sekolah);

        $response = $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', ['sumber_dana' => 'BOS', 'jenis' => 'stok']));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_operator_dapat_export_buku_pengeluaran_excel(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap6');
        $barang = $this->barang('Semen');
        $this->terima($barang, 100, $sekolah);

        $response = $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', ['sumber_dana' => 'BOS', 'jenis' => 'pengeluaran']));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_export_menolak_jenis_tidak_dikenal(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->user($sekolah, 'op.lap7');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', ['sumber_dana' => 'BOS', 'jenis' => 'sembarang']))
            ->assertSessionHasErrors('jenis');
    }

    public function test_user_tanpa_permission_cetak_tidak_dapat_export(): void
    {
        $sekolah = $this->sekolah();
        // bendahara_sekolah punya lihat-distribusi-bhp tetapi tidak cetak-dokumen-bhp.
        $user = $this->user($sekolah, 'bend.lap', 'bendahara_sekolah');

        $this->actingAs($user)
            ->get(route('distribusi-bhp.laporan.export', ['sumber_dana' => 'BOS', 'jenis' => 'stok']))
            ->assertForbidden();
    }
}
