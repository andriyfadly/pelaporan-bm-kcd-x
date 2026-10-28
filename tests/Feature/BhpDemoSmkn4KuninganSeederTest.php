<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\DemoDistribusiBhpSmkn4KuninganSeeder;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpDemoSmkn4KuninganSeederTest extends TestCase
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
    }

    private function seedDemo(): void
    {
        $this->seed(DemoDistribusiBhpSmkn4KuninganSeeder::class);
    }

    /** @return array<string,int> */
    private function hitung(string $dana): array
    {
        return SumberDana::dengan($dana, fn (): array => [
            'barang' => Barang::where('kode_barang', 'like', DemoDistribusiBhpSmkn4KuninganSeeder::PENANDA.'%')->count(),
            'penerimaan' => Penerimaan::where('sekolah_id', $this->sekolah->id)->count(),
            'pengajuan' => Pengajuan::where('sekolah_id', $this->sekolah->id)->count(),
            'approved' => Pengajuan::where('sekolah_id', $this->sekolah->id)->where('status', 'approved')->count(),
            'rejected' => Pengajuan::where('sekolah_id', $this->sekolah->id)->where('status', 'rejected')->count(),
            'submitted' => Pengajuan::where('sekolah_id', $this->sekolah->id)->where('status', 'submitted')->count(),
            'dokumen' => Dokumen::whereIn('bhp_pengajuan_id', Pengajuan::where('sekolah_id', $this->sekolah->id)->pluck('id'))->count(),
        ]);
    }

    public function test_seeder_membuat_dataset_lengkap_untuk_bos_dan_bopd(): void
    {
        $this->seedDemo();

        foreach (SumberDana::SEMUA as $dana) {
            $hasil = $this->hitung($dana);

            $this->assertSame(10, $hasil['barang'], "Barang {$dana}");
            // 1 saldo awal + 3 penerimaan x 6 bulan.
            $this->assertSame(19, $hasil['penerimaan'], "Penerimaan {$dana}");
            // 4 pengajuan x 6 bulan + 1 ditolak + 1 menunggu.
            $this->assertSame(26, $hasil['pengajuan'], "Pengajuan {$dana}");
            $this->assertSame(24, $hasil['approved'], "Approved {$dana}");
            $this->assertSame(1, $hasil['rejected'], "Rejected {$dana}");
            $this->assertSame(1, $hasil['submitted'], "Submitted {$dana}");
            // 3 dokumen wajib x 24 approval + 4 tambahan + 6 stok opname bulanan.
            $this->assertSame(82, $hasil['dokumen'], "Dokumen {$dana}");
        }
    }

    /**
     * Dataset demo harus memuat tiga skenario pembayaran sekaligus supaya
     * kolom Keterangan "Lunas Bayar" dan halaman Lunas Bayar teruji apa adanya.
     */
    public function test_seeder_membagi_tiga_skenario_pembayaran(): void
    {
        $this->seedDemo();

        foreach (SumberDana::SEMUA as $dana) {
            $hasil = SumberDana::dengan($dana, fn (): array => [
                'belum' => Penerimaan::where('sekolah_id', $this->sekolah->id)
                    ->where('is_saldo_awal', false)->whereNull('no_kwitansi')->count(),
                'bulan_sama' => Penerimaan::where('sekolah_id', $this->sekolah->id)
                    ->whereNotNull('no_kwitansi')->whereColumn('tanggal_pembayaran', 'tanggal_bast')->count(),
                'bulan_depan' => Penerimaan::where('sekolah_id', $this->sekolah->id)
                    ->whereNotNull('no_kwitansi')->whereColumn('tanggal_pembayaran', '>', 'tanggal_bast')->count(),
            ]);

            $this->assertSame(6, $hasil['belum'], "Belum dibayar {$dana}");
            $this->assertSame(6, $hasil['bulan_sama'], "Dibayar bulan sama {$dana}");
            $this->assertSame(6, $hasil['bulan_depan'], "Dibayar bulan depan {$dana}");
        }
    }

    public function test_seeder_idempotent_dijalankan_dua_kali(): void
    {
        $this->seedDemo();
        $awal = [];

        foreach (SumberDana::SEMUA as $dana) {
            $awal[$dana] = $this->hitung($dana);
        }

        $this->seedDemo();

        foreach (SumberDana::SEMUA as $dana) {
            $this->assertSame($awal[$dana], $this->hitung($dana), "Jumlah baris {$dana} berubah saat seeder diulang.");
        }
    }

    public function test_seeder_gagal_jelas_bila_sekolah_tidak_ada(): void
    {
        $this->sekolah->update(['npsn' => '00000000']);

        $this->expectExceptionMessage('Sekolah NPSN '.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN.' tidak ditemukan');

        $this->seedDemo();
    }

    public function test_tiga_pejabat_berlaku_sepanjang_semester(): void
    {
        $this->seedDemo();

        $service = app(PenugasanPegawaiService::class);

        foreach (['2026-01-01', '2026-03-31', '2026-06-30'] as $tanggal) {
            foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
                $penugasan = $service->berlakuPada($this->sekolah->id, $kode, $tanggal);

                $this->assertNotNull($penugasan, "Jabatan {$kode} tidak berlaku pada {$tanggal}.");
                $this->assertNotEmpty($penugasan->pegawai?->nama_lengkap, "Nama pejabat {$kode} kosong.");
            }
        }
    }

    public function test_pengguna_demo_tetap_terhubung_ke_sekolah_dan_divisi(): void
    {
        $this->seedDemo();

        $operator = User::where('username', 'demo-operator-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN)->firstOrFail();
        $this->assertSame($this->sekolah->id, $operator->sekolah_id);
        $this->assertTrue($operator->hasRole('operator_sekolah'));

        $divisiUser = User::where('username', 'like', 'demo-'.DemoDistribusiBhpSmkn4KuninganSeeder::NPSN.'-%')->get();
        $this->assertCount(4, $divisiUser);
        $this->assertTrue($divisiUser->every(fn (User $u): bool => $u->divisi_id !== null && $u->hasRole('operator_divisi')));
        $this->assertSame(4, $divisiUser->pluck('divisi_id')->unique()->count());
    }

    public function test_seeder_tidak_menyentuh_data_sekolah_lain(): void
    {
        $lain = Sekolah::create(['nama_sekolah' => 'SMKN LAIN DEMO', 'kota_kab' => 'BANDUNG']);

        SumberDana::dengan('BOS', function () use ($lain): void {
            $barang = Barang::create(['kode_barang' => 'LAIN-1', 'nama_barang' => 'Barang Lain', 'satuan' => 'Pcs']);
            $penerimaan = Penerimaan::create(['sekolah_id' => $lain->id, 'is_saldo_awal' => true, 'periode_tahun' => 2026, 'no_bast' => 'LAIN']);
            $penerimaan->details()->create(['barang_id' => $barang->id, 'jumlah' => 7, 'harga_satuan' => 1000, 'nilai_total' => 7000]);
        });

        $this->seedDemo();

        SumberDana::dengan('BOS', function () use ($lain): void {
            $this->assertSame(1, Penerimaan::where('sekolah_id', $lain->id)->count());
            $this->assertSame(1, Barang::where('kode_barang', 'LAIN-1')->count());
        });
    }
}
