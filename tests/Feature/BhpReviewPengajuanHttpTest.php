<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\StokService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jalur sukses Review Pengajuan BHP lewat HTTP: halaman review, approval dengan
 * koreksi jumlah, dan penolakan. Sebelumnya semua test hanya menguji jalur
 * penolakan (403/gate), sehingga controller-nya tidak pernah benar-benar jalan.
 */
class BhpReviewPengajuanHttpTest extends TestCase
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

        $this->sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Review', 'kota_kab' => 'Kota Bandung']);

        $this->pembuat = User::create([
            'name' => 'Pembuat',
            'username' => 'op.review',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->pembuat->assignRole('operator_sekolah');

        $this->reviewer = User::create([
            'name' => 'Reviewer',
            'username' => 'rev.review',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        // `approve-pengajuan-bhp` hanya dimiliki admin_kcd; dengan sekolah_id
        // terisi, resolveSekolahId tetap mengunci ke sekolah sendiri.
        $this->reviewer->assignRole('admin_kcd');

        $this->barang = Barang::create([
            'kode_barang' => '1.1.7.30.400',
            'nama_barang' => 'Kertas A4',
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
            'jumlah' => 20,
            'harga_satuan' => 55_000,
            'nilai_total' => 1_100_000,
        ]);
    }

    private function lengkapiPejabat(): void
    {
        $pejabat = app(PenugasanPegawaiService::class);

        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $pejabat->simpan($this->sekolah->id, $this->reviewer->id, [
                'jabatan' => $kode,
                'nama' => 'Pejabat '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }
    }

    private function pengajuanSubmitted(int $jumlah): Pengajuan
    {
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->pembuat->id,
            'sumber_dana' => 'BOS',
            'status' => 'draft',
            'tanggal' => '2026-07-10',
        ]);
        $pengajuan->details()->create([
            'barang_id' => $this->barang->id,
            'jumlah_diminta' => $jumlah,
        ]);

        app(StokService::class)->submit($pengajuan->fresh());

        return $pengajuan->fresh();
    }

    public function test_halaman_review_menampilkan_pengajuan_submitted(): void
    {
        $pengajuan = $this->pengajuanSubmitted(5);

        $this->actingAs($this->reviewer)
            ->get(route('distribusi-bhp.bos.review-pengajuan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Review/Index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $pengajuan->id)
                // Pejabat belum lengkap → peringatan dini tampil, approval tetap
                // akan ditolak oleh service.
                ->whereNot('pejabatWarning', null));
    }

    public function test_halaman_review_tanpa_antrean_tidak_menampilkan_peringatan(): void
    {
        $this->actingAs($this->reviewer)
            ->get(route('distribusi-bhp.bos.review-pengajuan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 0)
                ->where('pejabatWarning', null));
    }

    public function test_approve_dengan_koreksi_jumlah_membuat_dokumen(): void
    {
        $this->lengkapiPejabat();
        $pengajuan = $this->pengajuanSubmitted(8);
        $detail = $pengajuan->details()->firstOrFail();

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.approve', $pengajuan), [
                'jumlah_disetujui' => [$detail->id => 5],
                'review_notes' => 'Stok dibatasi 5 rim.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $pengajuan->refresh();
        $this->assertSame('approved', $pengajuan->status);
        $this->assertSame('Stok dibatasi 5 rim.', $pengajuan->review_notes);
        $this->assertSame(5, (int) $pengajuan->details()->value('jumlah_disetujui'));
        $this->assertSame(3, $pengajuan->dokumen()->count());
        $this->assertSame(5, app(StokService::class)->terpakai($this->barang, $this->sekolah->id));
        $this->assertSame(15, app(StokService::class)->tersedia($this->barang, $this->sekolah->id));
    }

    public function test_approve_gagal_karena_pejabat_belum_lengkap_memberi_flash_error(): void
    {
        $pengajuan = $this->pengajuanSubmitted(4);

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.approve', $pengajuan))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(4, app(StokService::class)->reservasi($this->barang, $this->sekolah->id));
        $this->assertSame(0, $pengajuan->fresh()->dokumen()->count());
    }

    public function test_reject_melepas_reservasi_lewat_http(): void
    {
        $pengajuan = $this->pengajuanSubmitted(6);

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.reject', $pengajuan), [
                'rejection_reason' => 'Kebutuhan ditunda.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $pengajuan->refresh();
        $this->assertSame('rejected', $pengajuan->status);
        $this->assertSame('Kebutuhan ditunda.', $pengajuan->rejection_reason);
        $this->assertSame($this->reviewer->id, $pengajuan->reviewed_by);
        $this->assertNotNull($pengajuan->reviewed_at);
        $this->assertSame(0, app(StokService::class)->reservasi($this->barang, $this->sekolah->id));
        $this->assertSame(20, app(StokService::class)->tersedia($this->barang, $this->sekolah->id));
    }

    public function test_reject_wajib_alasan(): void
    {
        $pengajuan = $this->pengajuanSubmitted(6);

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.reject', $pengajuan), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('submitted', $pengajuan->fresh()->status);
    }

    public function test_reject_pengajuan_yang_sudah_disetujui_memberi_flash_error(): void
    {
        $this->lengkapiPejabat();
        $pengajuan = $this->pengajuanSubmitted(4);

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.approve', $pengajuan))
            ->assertSessionHas('success');

        $this->actingAs($this->reviewer)
            ->post(route('distribusi-bhp.bos.review-pengajuan.reject', $pengajuan), [
                'rejection_reason' => 'Batal.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('approved', $pengajuan->fresh()->status);
    }

    public function test_approve_pengajuan_sendiri_403(): void
    {
        $pengajuan = $this->pengajuanSubmitted(4);

        $this->actingAs($this->pembuat)
            ->post(route('distribusi-bhp.bos.review-pengajuan.approve', $pengajuan))
            ->assertForbidden();
    }
}
