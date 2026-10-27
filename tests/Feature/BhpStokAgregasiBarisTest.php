<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
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
use RuntimeException;
use Tests\TestCase;

/**
 * Satu barang boleh muncul di beberapa baris pengajuan. Reservasi dan approval
 * wajib dihitung per BARANG (total baris), bukan per baris — kalau tidak, stok
 * 10 bisa direservasi/disetujui 6 + 6 dan stok jadi minus.
 */
class BhpStokAgregasiBarisTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private User $reviewer;

    private Barang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Agregasi', 'kota_kab' => 'Kota Bandung']);

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.agregasi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');

        $this->reviewer = User::create([
            'name' => 'Reviewer',
            'username' => 'rev.agregasi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->reviewer->assignRole('operator_sekolah');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.900',
            'nama_barang' => 'Kertas HVS',
            'satuan' => 'Rim',
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
            'harga_satuan' => 50_000,
            'nilai_total' => 500_000,
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

    private function draftDuaBaris(): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-07-10',
        ]);

        return $pengajuan;
    }

    public function test_submit_menolak_total_baris_melebihi_stok(): void
    {
        $pengajuan = $this->draftDuaBaris();
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak mencukupi');

        app(StokService::class)->submit($pengajuan->fresh());
    }

    public function test_submit_membolehkan_total_baris_pas_dengan_stok(): void
    {
        $pengajuan = $this->draftDuaBaris();
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 4]);
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);

        app(StokService::class)->submit($pengajuan->fresh());

        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(0, app(StokService::class)->tersedia($this->barang, $this->sekolah->id));
    }

    public function test_approve_menolak_total_baris_melebihi_stok(): void
    {
        $pengajuan = $this->draftDuaBaris();
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);

        // Reservasi dibuat langsung untuk mensimulasikan data yang lolos
        // sebelum validasi agregat ada.
        $pengajuan->details()->update(['status_reservasi' => 'direservasi', 'jumlah_direservasi' => 6]);
        $pengajuan->update(['status' => 'submitted']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('melebihi stok tersedia');

        app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);
    }

    public function test_approve_membolehkan_total_baris_pas_dengan_stok(): void
    {
        $pengajuan = $this->draftDuaBaris();
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 4]);
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 6]);

        app(StokService::class)->submit($pengajuan->fresh());
        app(DokumenService::class)->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        $stok = app(StokService::class);
        $this->assertSame('approved', $pengajuan->fresh()->status);
        $this->assertSame(10, $stok->terpakai($this->barang, $this->sekolah->id));
        $this->assertSame(0, $stok->tersedia($this->barang, $this->sekolah->id));
    }

    public function test_approve_kedua_kali_ditolak_tanpa_dokumen_ganda(): void
    {
        $pengajuan = $this->draftDuaBaris();
        $pengajuan->details()->create(['barang_id' => $this->barang->id, 'jumlah_diminta' => 5]);

        app(StokService::class)->submit($pengajuan->fresh());

        $service = app(DokumenService::class);
        $service->approveDanGenerate($pengajuan->fresh(), $this->reviewer);

        try {
            $service->approveDanGenerate($pengajuan->fresh(), $this->reviewer);
            $this->fail('Approval kedua seharusnya ditolak.');
        } catch (RuntimeException) {
            // Ditolak: baik karena dokumen sudah ada maupun status bukan submitted.
        }

        $this->assertSame(3, $pengajuan->fresh()->dokumen()->count(), 'Dokumen wajib tidak boleh dobel.');
        $this->assertSame(5, app(StokService::class)->terpakai($this->barang, $this->sekolah->id));
    }
}
