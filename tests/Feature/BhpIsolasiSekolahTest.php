<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\LaporanService;
use App\Services\DistribusiBhp\StokService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpIsolasiSekolahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama): Sekolah
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

    private function barang(): Barang
    {
        return Barang::create([
            'kode_barang' => '1.1.7.50.ISO',
            'nama_barang' => 'Semen Isolasi',
            'satuan' => 'Sak',
            'sumber_dana' => 'BOS',
        ]);
    }

    private function terima(Barang $barang, int $jumlah, Sekolah $sekolah): void
    {
        $penerimaan = Penerimaan::create([
            'sekolah_id' => $sekolah->id,
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

    public function test_stok_sekolah_tidak_mengonsumsi_penerimaan_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A Isolasi');
        $sekolahB = $this->sekolah('SMKN B Isolasi');
        $barang = $this->barang();

        $this->terima($barang, 100, $sekolahA);
        $this->terima($barang, 50, $sekolahB);

        $stok = app(StokService::class);

        $this->assertSame(100, $stok->fisik($barang, $sekolahA->id));
        $this->assertSame(50, $stok->fisik($barang, $sekolahB->id));
        // Admin (tanpa scope) melihat semuanya.
        $this->assertSame(150, $stok->fisik($barang));
    }

    public function test_stok_sekolah_tidak_melihat_penerimaan_tanpa_sekolah_milik_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN Pool A');
        $sekolahB = $this->sekolah('SMKN Pool B');
        $barang = $this->barang();

        // Tidak ada pool pusat: stok sekolah A tidak pernah terlihat sekolah B,
        // dan sebaliknya (tidak ada stok bersama).
        $this->terima($barang, 60, $sekolahA);

        $stok = app(StokService::class);

        $this->assertSame(60, $stok->fisik($barang, $sekolahA->id));
        $this->assertSame(0, $stok->fisik($barang, $sekolahB->id));
    }

    public function test_dua_sekolah_tidak_bisa_oversell_stok_yang_sama(): void
    {
        $sekolahA = $this->sekolah('SMKN Oversell A');
        $sekolahB = $this->sekolah('SMKN Oversell B');
        $userA = $this->user($sekolahA, 'op.iso.over.a');
        $userB = $this->user($sekolahB, 'op.iso.over.b');
        $barang = $this->barang();

        // Stok hanya ada di sekolah A.
        $this->terima($barang, 30, $sekolahA);

        $pengajuanA = Pengajuan::create([
            'sekolah_id' => $sekolahA->id,
            'user_id' => $userA->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuanA->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 30]);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $userB->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-06-17',
        ]);
        $pengajuanB->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 30]);

        $stok = app(StokService::class);
        $stok->submit($pengajuanA->fresh('details'));

        // Sekolah B tidak boleh ikut mengonsumsi stok milik sekolah A.
        $this->expectException(\RuntimeException::class);

        $stok->submit($pengajuanB->fresh('details'));
    }

    public function test_laporan_stok_operator_hanya_melihat_sekolahnya(): void
    {
        $sekolahA = $this->sekolah('SMKN Lap A');
        $sekolahB = $this->sekolah('SMKN Lap B');
        $userA = $this->user($sekolahA, 'op.iso.lap');
        $userB = $this->user($sekolahB, 'op.iso.lap2');
        $barang = $this->barang();

        $this->terima($barang, 100, $sekolahA);

        // Pengajuan approved milik sekolah B.
        $approved = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $userB->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $approved->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 25, 'jumlah_disetujui' => 25]);

        $rows = app(LaporanService::class)->laporanStok('BOS', $sekolahA->id);

        $this->assertSame(100, $rows[0]['fisik']);
        $this->assertSame(0, $rows[0]['terpakai']);
        $this->assertSame(100, $rows[0]['tersedia']);
    }

    public function test_operator_sekolah_tidak_melihat_pengeluaran_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN Buku A');
        $sekolahB = $this->sekolah('SMKN Buku B');
        $userA = $this->user($sekolahA, 'op.iso.buku');
        $userB = $this->user($sekolahB, 'op.iso.buku2');
        $barang = $this->barang();

        $approvedB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $userB->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $approvedB->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 10, 'jumlah_disetujui' => 10]);

        $approvedA = Pengajuan::create([
            'sekolah_id' => $sekolahA->id,
            'user_id' => $userA->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-19',
        ]);
        $approvedA->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 5, 'jumlah_disetujui' => 5]);

        $buku = app(LaporanService::class)->bukuPengeluaran('BOS', $sekolahA->id);

        $this->assertCount(1, $buku);
        $this->assertSame(5, $buku[0]['jumlah']);
    }

    public function test_kartu_persediaan_mengabaikan_mutasi_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN Kartu A');
        $sekolahB = $this->sekolah('SMKN Kartu B');
        $userA = $this->user($sekolahA, 'op.iso.kartu');
        $userB = $this->user($sekolahB, 'op.iso.kartu2');
        $barang = $this->barang();

        $this->terima($barang, 100, $sekolahA);
        $this->terima($barang, 999, $sekolahB);

        $keluarB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $userB->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-20',
        ]);
        $keluarB->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 500, 'jumlah_disetujui' => 500]);

        $keluarA = Pengajuan::create([
            'sekolah_id' => $sekolahA->id,
            'user_id' => $userA->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-21',
        ]);
        $keluarA->details()->create(['barang_id' => $barang->id, 'jumlah_diminta' => 20, 'jumlah_disetujui' => 20]);

        $kartu = app(LaporanService::class)->kartuPersediaan($barang->id, 'BOS', $sekolahA->id);

        $this->assertCount(2, $kartu['mutasi']);
        $this->assertSame(100, $kartu['barang']['total_masuk']);
        $this->assertSame(20, $kartu['barang']['total_keluar']);
        $this->assertSame(80, $kartu['barang']['saldo_akhir']);
    }

    public function test_operator_dapat_export_laporan_scoped_sekolahnya(): void
    {
        $sekolahA = $this->sekolah('SMKN Exp A');
        $sekolahB = $this->sekolah('SMKN Exp B');
        $userA = $this->user($sekolahA, 'op.iso.exp');
        $barang = $this->barang();

        $this->terima($barang, 100, $sekolahA);
        $this->terima($barang, 999, $sekolahB);

        $this->actingAs($userA)
            ->get(route('distribusi-bhp.bos.laporan.export', ['jenis' => 'stok']))
            ->assertOk();
    }

    public function test_sumber_dana_get_tidak_valid_ditolak(): void
    {
        $sekolah = $this->sekolah('SMKN Get Guard');
        $user = $this->user($sekolah, 'op.iso.guard');

        $this->actingAs($user)
            ->get('/distribusi-bhp/pengajuan?sumber_dana=APBN')
            ->assertStatus(422);
    }
}
