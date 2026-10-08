<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\DistribusiBhp\PengajuanDetail;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpStokDanPengajuanTest extends TestCase
{
    use RefreshDatabase;

    private function sekolah(): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => 'SMKN 1 BHP', 'kota_kab' => 'Kota Bandung']);
    }

    private function barang(string $sumberDana = 'BOS', int $stok = 100): Barang
    {
        $barang = Barang::create([
            'kode_barang' => '1.1.7.01.0'.$stok.$sumberDana,
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);

        if ($stok > 0) {
            $penerimaan = Penerimaan::create([
                'sumber_dana' => $sumberDana,
                'is_saldo_awal' => true,
                'periode_tahun' => 2026,
            ]);
            PenerimaanDetail::create([
                'bhp_penerimaan_id' => $penerimaan->id,
                'barang_id' => $barang->id,
                'jumlah' => $stok,
                'harga_satuan' => 50000,
                'nilai_total' => $stok * 50000,
            ]);
        }

        return $barang;
    }

    private function pengajuan(Sekolah $sekolah, User $user, string $sumberDana = 'BOS', string $status = 'draft'): Pengajuan
    {
        return Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'sumber_dana' => $sumberDana,
            'status' => $status,
            'tanggal' => '2026-06-17',
        ]);
    }

    public function test_stok_tersedia_menggunakan_rumus_fisik_minus_terpakai_minus_reservasi(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.tkj', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 100);

        $submitted = $this->pengajuan($sekolah, $user, 'BOS', 'submitted');
        PengajuanDetail::create(['bhp_pengajuan_id' => $submitted->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 30, 'status_reservasi' => 'direservasi', 'jumlah_direservasi' => 30]);

        $approved = $this->pengajuan($sekolah, $user, 'BOS', 'approved');
        PengajuanDetail::create(['bhp_pengajuan_id' => $approved->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 20, 'jumlah_disetujui' => 20]);

        $service = app(StokService::class);

        $this->assertSame(100, $service->fisik($barang));
        $this->assertSame(20, $service->terpakai($barang));
        $this->assertSame(30, $service->reservasi($barang));
        $this->assertSame(50, $service->tersedia($barang));
    }

    public function test_stok_tersedia_tidak_pernah_negatif(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.rpl', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 20);

        $approved = $this->pengajuan($sekolah, $user, 'BOS', 'approved');
        PengajuanDetail::create(['bhp_pengajuan_id' => $approved->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 50, 'jumlah_disetujui' => 50]);

        $this->assertSame(0, app(StokService::class)->tersedia($barang));
    }

    public function test_sumber_dana_terisolasi_pada_stok(): void
    {
        $bos = $this->barang('BOS', 40);
        $bopd = $this->barang('BOPD', 10);
        $service = app(StokService::class);

        $this->assertSame(40, $service->tersedia($bos));
        $this->assertSame(10, $service->tersedia($bopd));
    }

    public function test_submit_membuat_reservasi_dan_approve_mengubah_menjadi_pemakaian(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.mm', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.bhp2', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 100);

        $pengajuan = $this->pengajuan($sekolah, $user);
        $detail = PengajuanDetail::create(['bhp_pengajuan_id' => $pengajuan->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 25]);

        $service = app(StokService::class);
        $service->submit($pengajuan->fresh('details'));

        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(25, $service->reservasi($barang));
        $this->assertSame(75, $service->tersedia($barang));

        $service->approve($pengajuan->fresh('details'), $admin, [$detail->id => 25]);

        $this->assertSame('approved', $pengajuan->fresh()->status);
        $this->assertSame(0, $service->reservasi($barang));
        $this->assertSame(25, $service->terpakai($barang));
        $this->assertSame(75, $service->tersedia($barang));
    }

    public function test_reject_melepaskan_reservasi(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.akl', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.bhp3', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 100);

        $pengajuan = $this->pengajuan($sekolah, $user);
        PengajuanDetail::create(['bhp_pengajuan_id' => $pengajuan->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 25]);

        $service = app(StokService::class);
        $service->submit($pengajuan->fresh('details'));
        $this->assertSame(25, $service->reservasi($barang));

        $service->reject($pengajuan->fresh('details'), $admin, 'Stok dialihkan ke kebutuhan lain');

        $this->assertSame('rejected', $pengajuan->fresh()->status);
        $this->assertSame(0, $service->reservasi($barang));
        $this->assertSame(0, $service->terpakai($barang));
        $this->assertSame(100, $service->tersedia($barang));
    }

    public function test_submit_ditolak_bila_stok_tidak_cukup(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.tkr', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 10);

        $pengajuan = $this->pengajuan($sekolah, $user);
        PengajuanDetail::create(['bhp_pengajuan_id' => $pengajuan->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 999]);

        $this->expectException(\RuntimeException::class);
        app(StokService::class)->submit($pengajuan->fresh('details'));
    }

    public function test_approve_dua_kali_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $user = User::create(['name' => 'Divisi', 'username' => 'divisi.titl', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.bhp4', 'password' => bcrypt('password'), 'sekolah_id' => $sekolah->id]);
        $barang = $this->barang('BOS', 100);

        $pengajuan = $this->pengajuan($sekolah, $user);
        $detail = PengajuanDetail::create(['bhp_pengajuan_id' => $pengajuan->id, 'barang_id' => $barang->id, 'jumlah_diminta' => 10]);

        $service = app(StokService::class);
        $service->submit($pengajuan->fresh('details'));
        $service->approve($pengajuan->fresh('details'), $admin, [$detail->id => 10]);

        $this->expectException(\RuntimeException::class);
        $service->approve($pengajuan->fresh('details'), $admin, [$detail->id => 10]);
    }
}
