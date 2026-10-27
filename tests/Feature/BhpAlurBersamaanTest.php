<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\NomorSequence;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\NomorDokumenService;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Perilaku saat dua permintaan bertemu pada data yang sama. SQLite test tidak
 * menjalankan transaksi paralel sungguhan, jadi yang diuji adalah kontraknya:
 * state dicek ulang di dalam transaksi, reservasi tidak oversell, nomor dokumen
 * unik, dan dokumen tidak pernah dobel.
 */
class BhpAlurBersamaanTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $pembuat;

    private User $reviewer;

    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Bersamaan', 'kota_kab' => 'Kota Bandung']);

        $this->pembuat = User::create([
            'name' => 'Pembuat',
            'username' => 'op.bersamaan',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->pembuat->assignRole('operator_sekolah');

        $this->reviewer = User::create([
            'name' => 'Reviewer',
            'username' => 'rev.bersamaan',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->reviewer->assignRole('operator_sekolah');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.600',
            'nama_barang' => 'Spidol',
            'satuan' => 'Buah',
            'sumber_dana' => 'BOS',
        ]);

        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $this->barang->id,
            'jumlah' => 10,
            'harga_satuan' => 15_000,
            'nilai_total' => 150_000,
        ]);

        $pejabat = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $pejabat->simpan($this->sekolah->id, $this->reviewer->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }
    }

    private function pengajuan(int $jumlah, string $status = 'draft', ?User $pemilik = null): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => ($pemilik ?? $this->pembuat)->id,
            'sumber_dana' => 'BOS',
            'status' => $status,
            'tanggal' => '2026-07-10',
        ]);

        $pengajuan->details()->create([
            'barang_id' => $this->barang->id,
            'jumlah_diminta' => $jumlah,
        ]);

        return $pengajuan;
    }

    public function test_dua_pengajuan_tidak_bisa_mereservasi_stok_yang_sama(): void
    {
        $a = $this->pengajuan(6);
        $b = $this->pengajuan(6);

        $stok = app(StokService::class);
        $stok->submit($a->fresh());

        try {
            $stok->submit($b->fresh());
            $this->fail('Submit kedua seharusnya ditolak karena stok hanya tersisa 4.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        $this->assertSame('draft', $b->fresh()->status);
        $this->assertSame(4, $stok->tersedia($this->barang, $this->sekolah->id));
    }

    public function test_pengajuan_yang_sudah_submitted_tidak_bisa_disubmit_lagi(): void
    {
        $pengajuan = $this->pengajuan(3);
        $stok = app(StokService::class);

        $stok->submit($pengajuan->fresh());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('draft');

        $stok->submit($pengajuan->fresh());
    }

    public function test_reject_setelah_approve_ditolak_dan_stok_tidak_berubah(): void
    {
        $pengajuan = $this->pengajuan(4);

        $stok = app(StokService::class);
        $stok->submit($pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        try {
            $stok->reject($pengajuan->fresh(), $this->reviewer, 'Batal');
            $this->fail('Reject setelah approve seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('submitted', $e->getMessage());
        }

        $this->assertSame('approved', $pengajuan->fresh()->status);
        $this->assertSame(4, $stok->terpakai($this->barang, $this->sekolah->id));
        $this->assertSame(0, $stok->reservasi($this->barang, $this->sekolah->id));
    }

    public function test_approve_dua_pengajuan_berbeda_tidak_melebihi_stok(): void
    {
        $a = $this->pengajuan(6);
        $b = $this->pengajuan(4);

        $stok = app(StokService::class);
        $dokumen = app(DokumenService::class);

        $stok->submit($a->fresh());
        $stok->submit($b->fresh());

        $dokumen->approveDanGenerate($a->fresh(), $this->reviewer);

        // Koreksi stok fisik turun setelah approval pertama (mis. salah input
        // penerimaan): tidak ada sisa untuk pengajuan kedua.
        PenerimaanDetail::query()->update(['jumlah' => 6]);

        $this->assertGagal(fn () => $dokumen->approveDanGenerate($b->fresh(), $this->reviewer), 'melebihi stok tersedia');

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('submitted', $b->fresh()->status, 'Pengajuan kedua harus tetap submitted.');
        $this->assertSame(0, $b->fresh()->dokumen()->count(), 'Dokumen tidak boleh dibuat saat approval gagal.');
        $this->assertSame(6, $stok->terpakai($this->barang, $this->sekolah->id));
        $this->assertSame(4, $stok->reservasi($this->barang, $this->sekolah->id));
    }

    /** Jalankan aksi dan pastikan gagal dengan pesan tertentu (tanpa menelan fail()). */
    private function assertGagal(\Closure $aksi, string $pesan): void
    {
        try {
            $aksi();
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());

            return;
        }

        $this->fail("Aksi seharusnya gagal dengan pesan mengandung \"{$pesan}\".");
    }

    public function test_generate_dokumen_tambahan_dua_kali_mengembalikan_dokumen_sama(): void
    {
        $pengajuan = $this->pengajuan(3);
        $stok = app(StokService::class);
        $dokumenService = app(DokumenService::class);

        $stok->submit($pengajuan->fresh());
        $dokumenService->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        $pertama = $dokumenService->generateTambahan($pengajuan->fresh(), 'bast', '2026-07-31');
        $kedua = $dokumenService->generateTambahan($pengajuan->fresh(), 'bast', '2026-07-31');

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, Dokumen::where('bhp_pengajuan_id', $pengajuan->id)->where('jenis', 'bast')->count());
    }

    public function test_nomor_dokumen_berurutan_dan_unik_per_bulan(): void
    {
        $service = app(NomorDokumenService::class);

        $juli1 = $service->generate('bast', 'BOS', 2026, 7);
        $juli2 = $service->generate('bast', 'BOS', 2026, 7);
        $agustus = $service->generate('bast', 'BOS', 2026, 8);

        $this->assertNotSame($juli1, $juli2);
        $this->assertStringContainsString('001', $juli1);
        $this->assertStringContainsString('002', $juli2);
        $this->assertStringContainsString('001', $agustus, 'Counter bulan baru mulai dari 1.');

        $this->assertSame(1, NomorSequence::where('jenis', 'bast')->where('tahun', 2026)->where('bulan', 8)->value('counter'));
        $this->assertSame(2, NomorSequence::where('jenis', 'bast')->where('tahun', 2026)->where('bulan', 7)->value('counter'));
    }

    public function test_dokumen_tambahan_ditolak_untuk_pengajuan_belum_approved(): void
    {
        $pengajuan = $this->pengajuan(3, 'submitted');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah disetujui');

        app(DokumenService::class)->generateTambahan($pengajuan, 'bast');
    }

    public function test_jenis_dokumen_tambahan_asing_ditolak(): void
    {
        $pengajuan = $this->pengajuan(3);

        $this->expectException(\InvalidArgumentException::class);

        app(DokumenService::class)->generateTambahan($pengajuan, 'surat_palsu');
    }

    public function test_approval_gagal_tidak_meninggalkan_reservasi_terbuka(): void
    {
        $pengajuan = $this->pengajuan(4);
        $stok = app(StokService::class);
        $stok->submit($pengajuan->fresh());

        // Stok fisik hilang (mis. saldo awal dihapus langsung di DB) sehingga
        // approval harus gagal dan transaksi rollback.
        PenerimaanDetail::query()->update(['jumlah' => 0]);

        try {
            app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);
            $this->fail('Approval seharusnya gagal.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(4, $stok->reservasi($this->barang, $this->sekolah->id));
        $this->assertSame(0, $pengajuan->fresh()->dokumen()->count());
    }
}
