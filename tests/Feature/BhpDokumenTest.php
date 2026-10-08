<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\PejabatSekolah;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\NomorDokumenService;
use App\Services\DistribusiBhp\StokService;
use App\Services\PejabatSekolahService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BhpDokumenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Dokumen'): Sekolah
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

    private function barang(string $sumberDana = 'BOS', int $stok = 100): Barang
    {
        $barang = Barang::create([
            'kode_barang' => '1.1.7.20.'.$sumberDana,
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);

        $penerimaan = Penerimaan::create(['sumber_dana' => $sumberDana, 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => $stok,
            'harga_satuan' => 50000,
            'nilai_total' => $stok * 50000,
        ]);

        return $barang;
    }

    /** Approval mensyaratkan pejabat penandatangan lengkap. */
    private function lengkapiPejabat(Sekolah $sekolah, User $oleh): void
    {
        $service = app(PejabatSekolahService::class);
        foreach (PejabatSekolah::JABATAN as $i => $jabatan) {
            $service->simpan((string) $sekolah->id, (string) $oleh->id, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'pangkat_golongan' => 'III/d',
                'nip' => '19800101201001100'.$i,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }
    }

    public function test_nomor_dokumen_berurutan_dan_unik_per_sumber_dana_bulan_tahun(): void
    {
        $service = app(NomorDokumenService::class);

        $a = $service->generate('nota_permintaan', 'BOS', 2026, 6);
        $b = $service->generate('nota_permintaan', 'BOS', 2026, 6);
        $c = $service->generate('nota_permintaan', 'BOPD', 2026, 6);
        $d = $service->generate('nota_permintaan', 'BOS', 2026, 7);

        $this->assertSame('001/BHP/NPB/BOS/VI/2026', $a);
        $this->assertSame('002/BHP/NPB/BOS/VI/2026', $b);
        $this->assertSame('001/BHP/NPB/BOPD/VI/2026', $c);
        $this->assertSame('001/BHP/NPB/BOS/VII/2026', $d);
    }

    public function test_tabel_sequence_dan_dokumen_ada(): void
    {
        $this->assertTrue(Schema::hasTable('bhp_nomor_sequence'));
        $this->assertTrue(Schema::hasTable('bhp_dokumen'));
    }

    public function test_approve_pengajuan_membuat_dokumen_distribusi_secara_atomic(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.dok');
        $admin = $this->user($sekolah, 'admin.dok');
        $barang = $this->barang('BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 30,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 30,
        ]);

        $this->lengkapiPejabat($sekolah, $admin);
        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin, [$detail->id => 30]);

        $dokumen = $pengajuan->fresh()->dokumen;
        $this->assertGreaterThanOrEqual(3, $dokumen->count());
        $this->assertTrue($dokumen->pluck('jenis')->contains('nota_permintaan'));
        $this->assertTrue($dokumen->pluck('jenis')->contains('surat_permintaan'));
        $this->assertTrue($dokumen->pluck('jenis')->contains('surat_penyaluran'));
        $this->assertTrue($dokumen->every(fn ($d) => $d->nomor !== ''));

        // Semua dokumen dari satu approval berbagi tanggal titimangsa yang sama.
        $this->assertCount(1, $dokumen->pluck('tanggal_titimangsa')->unique());
    }

    public function test_approve_ulang_tidak_menduplikasi_dokumen(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.dok2');
        $admin = $this->user($sekolah, 'admin.dok2');
        $barang = $this->barang('BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 10,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 10,
        ]);

        $this->lengkapiPejabat($sekolah, $admin);
        $service = app(DokumenService::class);
        $service->approveDanGenerate($pengajuan->load('details'), $admin, [$detail->id => 10]);
        $jumlahAwal = $pengajuan->fresh()->dokumen()->count();

        try {
            $service->approveDanGenerate($pengajuan->fresh('details'), $admin, [$detail->id => 10]);
        } catch (\RuntimeException) {
            // diharapkan
        }

        $this->assertSame($jumlahAwal, $pengajuan->fresh()->dokumen()->count());
    }

    public function test_gagal_generate_me_rollback_approval_dan_stok(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.dok3');
        $admin = $this->user($sekolah, 'admin.dok3');
        // Stok fisik hanya 50, sementara item direservasi 100.
        $barang = $this->barang('BOS', 50);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 100,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 100,
        ]);

        $this->lengkapiPejabat($sekolah, $admin);
        try {
            app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin, [$detail->id => 100]);
            $this->fail('Approval seharusnya gagal karena stok tidak cukup.');
        } catch (\RuntimeException) {
            // diharapkan
        }

        // Tidak boleh ada setengah jalan: status tetap submitted, tidak ada dokumen.
        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(0, $pengajuan->fresh()->dokumen()->count());
        // Reservasi masih utuh karena approve gagal.
        $this->assertSame(100, app(StokService::class)->reservasi($barang));
    }

    public function test_operator_dapat_melihat_dokumen_pengajuan_yang_disetujui(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.dok4');
        $admin = $this->user($sekolah, 'admin.dok4');
        $barang = $this->barang('BOS', 100);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 5,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 5,
        ]);

        $this->lengkapiPejabat($sekolah, $admin);
        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin, [$detail->id => 5]);

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.dokumen.show', $pengajuan))
            ->assertOk();
    }
}
