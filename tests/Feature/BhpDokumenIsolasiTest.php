<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Dokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas tenant & sumber dana pada dokumen BHP: user sekolah lain tidak boleh
 * melihat dokumen, dan dokumen BOPD tidak boleh dicetak lewat URL BOS.
 */
class BhpDokumenIsolasiTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $reviewer;

    private Barang $barang;

    private Pengajuan $pengajuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Isolasi', 'kota_kab' => 'Kota Bandung']);

        $operator = User::create([
            'name' => 'Operator',
            'username' => 'op.isolasi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $operator->assignRole('operator_sekolah');

        $this->reviewer = User::create([
            'name' => 'Reviewer',
            'username' => 'rev.isolasi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->reviewer->assignRole('operator_sekolah');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.800',
            'nama_barang' => 'Tinta Printer',
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
            'jumlah' => 20,
            'harga_satuan' => 90_000,
            'nilai_total' => 1_800_000,
        ]);

        $pejabat = app(PenugasanPegawaiService::class);
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $pejabat->simpan($this->sekolah->id, $this->reviewer->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $this->pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-07-10',
        ]);
        $this->pengajuan->details()->create([
            'barang_id' => $this->barang->id,
            'jumlah_diminta' => 5,
        ]);

        app(StokService::class)->submit($this->pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($this->pengajuan->fresh(), $this->reviewer);
        $this->pengajuan = $this->pengajuan->fresh();
    }

    public function test_user_sekolah_lain_tidak_bisa_melihat_dokumen(): void
    {
        $lain = Sekolah::create(['nama_sekolah' => 'SMKN Lain Isolasi', 'kota_kab' => 'Kota Bandung']);
        $penyusup = User::create([
            'name' => 'Penyusup',
            'username' => 'op.penyusup',
            'password' => bcrypt('password'),
            'sekolah_id' => $lain->id,
            'password_changed_at' => now(),
        ]);
        $penyusup->assignRole('operator_sekolah');

        $dokumen = $this->pengajuan->dokumen()->firstOrFail();

        $this->actingAs($penyusup)
            ->get(route('distribusi-bhp.dokumen.show', $this->pengajuan))
            ->assertForbidden();

        $this->actingAs($penyusup)
            ->get(route('distribusi-bhp.dokumen.pdf', [$this->pengajuan, $dokumen]))
            ->assertForbidden();
    }

    public function test_dokumen_bopd_tidak_bisa_dicetak_lewat_url_bos(): void
    {
        $barangBopd = Barang::create([
            'kode_barang' => '1.1.7.30.801',
            'nama_barang' => 'Kertas BOPD',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOPD',
        ]);

        $penerimaan = Penerimaan::create([
            'sekolah_id' => $this->sekolah->id,
            'sumber_dana' => 'BOPD',
            'is_saldo_awal' => true,
            'periode_tahun' => 2026,
        ]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barangBopd->id,
            'jumlah' => 10,
            'harga_satuan' => 40_000,
            'nilai_total' => 400_000,
        ]);

        $pengajuanBopd = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->reviewer->id,
            'sumber_dana' => 'BOPD',
            'status' => 'draft',
            'tanggal' => '2026-07-11',
        ]);
        $pengajuanBopd->details()->create([
            'barang_id' => $barangBopd->id,
            'jumlah_diminta' => 2,
        ]);

        app(StokService::class)->submit($pengajuanBopd->fresh());
        app(DokumenService::class)->approveDanGenerate($pengajuanBopd->fresh(), $this->reviewer);

        $dokumenBopd = Dokumen::sumberDana('BOPD')
            ->where('bhp_pengajuan_id', $pengajuanBopd->id)
            ->firstOrFail();

        $this->assertSame('BOPD', $dokumenBopd->sumber_dana);

        $this->actingAs($this->reviewer)
            ->get(route('distribusi-bhp.bos.dokumen.pdf', [$pengajuanBopd, $dokumenBopd]))
            ->assertNotFound();
    }

    public function test_dokumen_milik_pengajuan_lain_404(): void
    {
        $dokumen = $this->pengajuan->dokumen()->firstOrFail();

        $lain = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->reviewer->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-07-12',
        ]);

        $this->actingAs($this->reviewer)
            ->get(route('distribusi-bhp.bos.dokumen.pdf', [$lain, $dokumen]))
            ->assertNotFound();
    }
}
