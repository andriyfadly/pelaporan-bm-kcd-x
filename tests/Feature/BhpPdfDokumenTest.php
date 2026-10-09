<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\BatchDokumen;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\PejabatSekolah;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\PejabatSekolahService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BhpPdfDokumenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN PDF'): Sekolah
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

    private function approvedPengajuan(Sekolah $sekolah, User $operator, User $reviewer): Pengajuan
    {
        // Approval mensyaratkan pejabat penandatangan lengkap.
        $pejabat = app(PejabatSekolahService::class);
        foreach (PejabatSekolah::JABATAN as $i => $jabatan) {
            $pejabat->simpan((string) $sekolah->id, (string) $reviewer->id, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'pangkat_golongan' => 'III/d',
                'nip' => '19800101201001100'.$i,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $barang = Barang::create([
            'kode_barang' => '1.1.7.40.BOS',
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => 'BOS',
        ]);

        $penerimaan = Penerimaan::create(['sekolah_id' => $sekolah->id, 'sumber_dana' => 'BOS', 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => 100,
            'harga_satuan' => 50000,
            'nilai_total' => 100 * 50000,
        ]);

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
            'tanggal' => '2026-06-17',
        ]);
        $detail = $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 20,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 20,
        ]);

        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $reviewer, [$detail->id => 20]);

        return $pengajuan->fresh('dokumen');
    }

    public function test_tabel_batch_dokumen_ada(): void
    {
        // Tabel terpisah per sumber dana.
        $this->assertTrue(Schema::hasTable('bhp_bos_batch_dokumen'));
        $this->assertTrue(Schema::hasTable('bhp_bopd_batch_dokumen'));
    }

    public function test_operator_dapat_mengunduh_pdf_satu_dokumen(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.pdf');
        $reviewer = $this->user($sekolah, 'rev.pdf');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);
        $dokumen = $pengajuan->dokumen->first();

        $response = $this->actingAs($operator)
            ->get(route('distribusi-bhp.dokumen.pdf', [$pengajuan, $dokumen]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_operator_dapat_membuat_batch_pdf_untuk_pengajuan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.pdf2');
        $reviewer = $this->user($sekolah, 'rev.pdf2');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $response = $this->actingAs($operator)
            ->post(route('distribusi-bhp.dokumen.batch', $pengajuan));

        // Batch mengembalikan satu PDF kompilasi sekaligus mencatat histori.
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $batch = BatchDokumen::sumberDana($pengajuan->sumber_dana)->firstOrFail();
        $this->assertSame('selesai', $batch->status);
        $this->assertSame($pengajuan->dokumen()->count(), $batch->total);
        $this->assertSame($batch->total, $batch->berhasil);
        $this->assertSame(0, $batch->gagal);
    }

    public function test_batch_pdf_mencatat_histori(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.pdf3');
        $reviewer = $this->user($sekolah, 'rev.pdf3');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $this->actingAs($operator)->post(route('distribusi-bhp.dokumen.batch', $pengajuan));

        $this->assertDatabaseHas('bhp_'.strtolower($pengajuan->sumber_dana).'_batch_dokumen', [
            'bhp_pengajuan_id' => $pengajuan->id,
            'status' => 'selesai',
        ]);
    }

    public function test_pdf_dokumen_menolak_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN PDF A');
        $sekolahB = $this->sekolah('SMKN PDF B');
        $operatorA = $this->user($sekolahA, 'op.a.pdf');
        $operatorB = $this->user($sekolahB, 'op.b.pdf');
        $reviewerB = $this->user($sekolahB, 'rev.b.pdf');
        $pengajuan = $this->approvedPengajuan($sekolahB, $operatorB, $reviewerB);
        $dokumen = $pengajuan->dokumen->first();

        $this->actingAs($operatorA)
            ->get(route('distribusi-bhp.dokumen.pdf', [$pengajuan, $dokumen]))
            ->assertForbidden();
    }

    public function test_user_tanpa_permission_cetak_tidak_dapat_pdf(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.pdf4');
        $reviewer = $this->user($sekolah, 'rev.pdf4');
        $bendahara = $this->user($sekolah, 'bend.pdf', 'bendahara_sekolah');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);
        $dokumen = $pengajuan->dokumen->first();

        $this->actingAs($bendahara)
            ->get(route('distribusi-bhp.dokumen.pdf', [$pengajuan, $dokumen]))
            ->assertForbidden();
    }
}
