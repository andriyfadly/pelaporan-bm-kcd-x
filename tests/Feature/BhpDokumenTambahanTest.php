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
use App\Services\PejabatSekolahService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpDokumenTambahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Dok Tambahan'): Sekolah
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

    private function approvedPengajuan(Sekolah $sekolah, User $operator, User $reviewer, string $sumberDana = 'BOS'): Pengajuan
    {
        // Approval mensyaratkan pejabat penandatangan lengkap.
        $service = app(PejabatSekolahService::class);
        foreach (PejabatSekolah::JABATAN as $jabatan) {
            $service->simpan((string) $sekolah->id, (string) $reviewer->id, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'pangkat_golongan' => 'III/d',
                'nip' => '198001012010011000'.strlen($jabatan),
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $barang = Barang::create([
            'kode_barang' => '1.1.7.50.'.$sumberDana,
            'nama_barang' => 'Semen Portland',
            'satuan' => 'Sak',
            'sumber_dana' => $sumberDana,
        ]);

        $penerimaan = Penerimaan::create(['sekolah_id' => $sekolah->id, 'sumber_dana' => $sumberDana, 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
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
            'sumber_dana' => $sumberDana,
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

    public function test_generate_bast_menambah_dokumen_pada_pengajuan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.bast');
        $reviewer = $this->user($sekolah, 'rev.bast');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $jumlahAwal = $pengajuan->dokumen()->count();

        app(DokumenService::class)->generateTambahan($pengajuan, 'bast', '2026-06-20');

        $pengajuan->refresh();
        $this->assertSame($jumlahAwal + 1, $pengajuan->dokumen()->count());

        $bast = $pengajuan->dokumen()->where('jenis', 'bast')->firstOrFail();
        $this->assertStringContainsString('BAST', $bast->nomor);
        $this->assertSame('2026-06-20', $bast->tanggal_titimangsa->toDateString());
    }

    public function test_generate_stok_opname_mencatat_payload_snapshot(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.so');
        $reviewer = $this->user($sekolah, 'rev.so');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-06-30');

        $so = $pengajuan->dokumen()->where('jenis', 'stok_opname')->firstOrFail();
        $this->assertSame('BOS', $so->payload['sumber_dana']);
        $this->assertArrayHasKey('items', $so->payload);
        $this->assertNotEmpty($so->payload['items']);
        // Snapshot stok: tersedia = fisik - terpakai - reservasi.
        $this->assertSame(80, $so->payload['items'][0]['tersedia']);
    }

    public function test_payload_snapshot_tidak_mencampur_data_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A Tambahan');
        $sekolahB = $this->sekolah('SMKN B Tambahan');
        $operator = $this->user($sekolahA, 'op.isolasi');
        $reviewer = $this->user($sekolahA, 'rev.isolasi');
        $pengajuan = $this->approvedPengajuan($sekolahA, $operator, $reviewer);

        // Sekolah B punya penerimaan dan pengeluaran pada sumber dana yang sama.
        $barang = Barang::create([
            'kode_barang' => '1.1.7.50.BOS-B',
            'nama_barang' => 'Kertas HVS B',
            'satuan' => 'Rim',
            'sumber_dana' => 'BOS',
        ]);
        $penerimaan = Penerimaan::create(['sekolah_id' => $sekolahB->id, 'sumber_dana' => 'BOS', 'is_saldo_awal' => true, 'periode_tahun' => 2026]);
        PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => 999,
            'harga_satuan' => 10000,
            'nilai_total' => 999 * 10000,
        ]);

        $pengajuanB = Pengajuan::create([
            'sekolah_id' => $sekolahB->id,
            'user_id' => $this->user($sekolahB, 'op.b2')->id,
            'sumber_dana' => 'BOS',
            'status' => 'approved',
            'tanggal' => '2026-06-18',
        ]);
        $pengajuanB->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 500,
            'jumlah_disetujui' => 500,
        ]);

        app(DokumenService::class)->generateTambahan($pengajuan, 'stok_opname', '2026-06-30');
        app(DokumenService::class)->generateTambahan($pengajuan, 'buku_pengeluaran', '2026-06-30');

        $so = $pengajuan->dokumen()->where('jenis', 'stok_opname')->firstOrFail();
        $itemSekolahA = collect($so->payload['items'])->firstWhere('barang_id', $pengajuan->details->first()->barang_id);
        $this->assertNotNull($itemSekolahA);
        // Stok sekolah A: fisik 100, terpakai 20 → tersedia 80 (bukan 999 dari sekolah B).
        $this->assertSame(100, $itemSekolahA['fisik']);
        $this->assertSame(80, $itemSekolahA['tersedia']);

        $buku = $pengajuan->dokumen()->where('jenis', 'buku_pengeluaran')->firstOrFail();
        // Buku pengeluaran hanya memuat pengeluaran sekolah A, bukan 500 milik sekolah B.
        $this->assertSame(20, collect($buku->payload['items'])->sum('jumlah'));
    }

    public function test_generate_tambahan_menolak_jenis_tidak_dikenal(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.x');
        $reviewer = $this->user($sekolah, 'rev.x');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $this->expectException(\InvalidArgumentException::class);

        app(DokumenService::class)->generateTambahan($pengajuan, 'sembarang_jenis');
    }

    public function test_generate_tambahan_idempotent_per_jenis(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.idem');
        $reviewer = $this->user($sekolah, 'rev.idem');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $service = app(DokumenService::class);
        $first = $service->generateTambahan($pengajuan, 'bap', '2026-06-21');
        $second = $service->generateTambahan($pengajuan->fresh('details'), 'bap');

        $this->assertSame($first->nomor, $second->nomor);
        $this->assertSame(1, $pengajuan->dokumen()->where('jenis', 'bap')->count());
    }

    public function test_operator_dapat_men_generate_bast_melalui_http(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.http');
        $reviewer = $this->user($sekolah, 'rev.http');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.dokumen.tambahan', $pengajuan), ['jenis' => 'bast', 'tanggal' => '2026-06-20'])
            ->assertRedirect();

        $this->assertDatabaseHas('bhp_'.strtolower($pengajuan->sumber_dana).'_dokumen', ['bhp_pengajuan_id' => $pengajuan->id, 'jenis' => 'bast']);
    }

    public function test_user_tanpa_permission_cetak_tidak_dapat_generate(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.perm');
        $reviewer = $this->user($sekolah, 'rev.perm');
        $bendahara = $this->user($sekolah, 'bend.perm', 'bendahara_sekolah');
        $pengajuan = $this->approvedPengajuan($sekolah, $operator, $reviewer);

        $this->actingAs($bendahara)
            ->post(route('distribusi-bhp.dokumen.tambahan', $pengajuan), ['jenis' => 'bast'])
            ->assertForbidden();
    }
}
