<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Jabatan;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\DokumenService;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpPejabatSekolahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Pejabat'): Sekolah
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

    /** Isi ketiga jabatan wajib untuk sekolah. */
    private function lengkapiPejabat(Sekolah $sekolah, string $prefix = 'pj'): void
    {
        $service = app(PenugasanPegawaiService::class);
        $userId = (string) $this->user($sekolah, $prefix.'.admin', 'admin_kcd')->id;

        foreach (Jabatan::KODE_PENANDATANGAN as $i => $jabatan) {
            $service->simpan((string) $sekolah->id, $userId, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat '.$jabatan,
                'pangkat_golongan' => 'III/d',
                'nip' => '19800101200'.$i.'01100'.$i,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }
    }

    private function submittedPengajuan(Sekolah $sekolah, User $operator): Pengajuan
    {
        $barang = Barang::create([
            'kode_barang' => '1.1.7.60.BOS',
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
        $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 10,
            'status_reservasi' => 'direservasi',
            'jumlah_direservasi' => 10,
        ]);

        return $pengajuan;
    }

    public function test_approval_gagal_jika_pejabat_belum_lengkap(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.gate');
        $admin = $this->user($sekolah, 'admin.gate', 'admin_kcd');
        $pengajuan = $this->submittedPengajuan($sekolah, $operator);

        try {
            app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin);
            $this->fail('Approval seharusnya gagal karena pejabat belum lengkap.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('belum lengkap', $e->getMessage());
        }

        // Tidak ada setengah jalan: status tetap submitted, reservasi utuh, tanpa dokumen.
        $this->assertSame('submitted', $pengajuan->fresh()->status);
        $this->assertSame(0, $pengajuan->fresh()->dokumen()->count());
        $this->assertSame(10, $pengajuan->fresh()->details->first()->jumlah_direservasi);
    }

    public function test_approval_berhasil_dengan_snapshot_penandatangan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.snap');
        $admin = $this->user($sekolah, 'admin.snap', 'admin_kcd');
        $this->lengkapiPejabat($sekolah);
        $pengajuan = $this->submittedPengajuan($sekolah, $operator);

        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin);

        $this->assertSame('approved', $pengajuan->fresh()->status);
        $pengajuan->fresh()->dokumen->each(function ($dokumen) {
            $this->assertArrayHasKey('penandatangan', $dokumen->payload);
            foreach (Jabatan::KODE_PENANDATANGAN as $jabatan) {
                $this->assertArrayHasKey($jabatan, $dokumen->payload['penandatangan']);
                $this->assertNotSame('', $dokumen->payload['penandatangan'][$jabatan]['nama']);
                $this->assertArrayHasKey('nip', $dokumen->payload['penandatangan'][$jabatan]);
            }
        });
    }

    public function test_reprint_tetap_memakai_snapshot_meski_pejabat_ganti(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.reprint');
        $admin = $this->user($sekolah, 'admin.reprint', 'admin_kcd');
        $this->lengkapiPejabat($sekolah);
        $pengajuan = $this->submittedPengajuan($sekolah, $operator);

        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin);

        // Kepala sekolah berganti.
        $jabatan = Jabatan::where('sekolah_id', $sekolah->id)->where('kode', 'kepala_sekolah')->firstOrFail();
        $lama = PenugasanPegawai::aktif((string) $sekolah->id, (string) $jabatan->id)->firstOrFail();
        app(PenugasanPegawaiService::class)->akhiri(
            (string) $sekolah->id,
            (string) $admin->id,
            $lama,
            '2026-06-30',
        );
        app(PenugasanPegawaiService::class)->simpan((string) $sekolah->id, (string) $admin->id, [
            'jabatan' => 'kepala_sekolah',
            'nama' => 'Kepala Baru',
            'mulai_menjabat' => '2026-07-01',
        ]);

        // Snapshot dokumen lama tidak berubah: nama sesuai saat approval, dan
        // saat itu pejabat masih aktif (selesai_menjabat null).
        $payload = $pengajuan->fresh()->dokumen->first()->payload;
        $this->assertSame('Pejabat kepala_sekolah', $payload['penandatangan']['kepala_sekolah']['nama']);
        $this->assertNull($payload['penandatangan']['kepala_sekolah']['selesai_menjabat']);
    }

    public function test_approval_backdate_memakai_pejabat_yang_berlaku_pada_tanggal_pengajuan(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.backdate');
        $admin = $this->user($sekolah, 'admin.backdate', 'admin_kcd');

        $service = app(PenugasanPegawaiService::class);
        $userId = (string) $admin->id;

        // Pejabat lama: 2025-01-01 s.d. 2025-12-31. Pengajuan backdate 2025-06-17.
        foreach (Jabatan::KODE_PENANDATANGAN as $jabatan) {
            $service->simpan((string) $sekolah->id, $userId, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat 2025',
                'mulai_menjabat' => '2025-01-01',
                'selesai_menjabat' => '2025-12-31',
            ]);
            $service->simpan((string) $sekolah->id, $userId, [
                'jabatan' => $jabatan,
                'nama' => 'Pejabat 2026',
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $pengajuan = $this->submittedPengajuan($sekolah, $operator);
        $pengajuan->update(['tanggal' => '2025-06-17']);

        app(DokumenService::class)->approveDanGenerate($pengajuan->load('details'), $admin);

        $payload = $pengajuan->fresh()->dokumen->first()->payload;
        $this->assertSame('Pejabat 2025', $payload['penandatangan']['kepala_sekolah']['nama']);
    }

    public function test_pejabat_satu_jabatan_hanya_satu_aktif(): void
    {
        $sekolah = $this->sekolah();
        $admin = $this->user($sekolah, 'admin.aktif', 'admin_kcd');
        $this->lengkapiPejabat($sekolah);

        // Menambah pejabat aktif untuk jabatan yang sama harus menutup yang lama (H-1).
        app(PenugasanPegawaiService::class)->simpan((string) $sekolah->id, (string) $admin->id, [
            'jabatan' => 'kepala_sekolah',
            'nama' => 'Kepala Kedua',
            'mulai_menjabat' => '2026-08-01',
        ]);

        $jabatan = Jabatan::where('sekolah_id', $sekolah->id)->where('kode', 'kepala_sekolah')->firstOrFail();
        $aktif = PenugasanPegawai::aktif((string) $sekolah->id, (string) $jabatan->id)->get();
        $this->assertSame(1, $aktif->count());
        $this->assertSame('Kepala Kedua', $aktif->first()->pegawai->nama_lengkap);

        $lama = PenugasanPegawai::whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'Pejabat kepala_sekolah'))->firstOrFail();
        $this->assertSame('2026-07-31', $lama->selesai_menjabat->toDateString());
    }

    public function test_periode_tumpang_tindih_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $admin = $this->user($sekolah, 'admin.overlap', 'admin_kcd');
        $service = app(PenugasanPegawaiService::class);

        $service->simpan((string) $sekolah->id, (string) $admin->id, [
            'jabatan' => 'wakasek_sarpras',
            'nama' => 'Waka A',
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => '2026-06-30',
        ]);

        try {
            $service->simpan((string) $sekolah->id, (string) $admin->id, [
                'jabatan' => 'wakasek_sarpras',
                'nama' => 'Waka B',
                'mulai_menjabat' => '2026-03-01',
                'selesai_menjabat' => '2026-12-31',
            ]);
            $this->fail('Periode tumpang tindih seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tumpang tindih', $e->getMessage());
        }
    }

    public function test_operator_tidak_bisa_kelola_pejabat_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN PA');
        $sekolahB = $this->sekolah('SMKN PB');
        $operatorA = $this->user($sekolahA, 'op.pa');
        $userId = (string) $this->user($sekolahB, 'op.pb', 'admin_kcd')->id;
        $pejabatB = app(PenugasanPegawaiService::class)->simpan((string) $sekolahB->id, $userId, [
            'jabatan' => 'kepala_sekolah',
            'nama' => 'Kepala B',
            'mulai_menjabat' => '2026-01-01',
        ]);

        $this->actingAs($operatorA)
            ->put(route('master-data.penugasan.update', $pejabatB), [
                'jabatan' => 'kepala_sekolah',
                'nama' => 'Diretas',
                'mulai_menjabat' => '2026-01-01',
            ])
            ->assertNotFound();

        $this->assertSame('Kepala B', $pejabatB->fresh()->pegawai->nama_lengkap);
    }

    public function test_operator_sekolah_dapat_melihat_dan_menambah_pejabat(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.crud');

        $this->actingAs($operator)
            ->get(route('master-data.penugasan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MasterData/Penugasan/Index'));

        $this->actingAs($operator)
            ->post(route('master-data.penugasan.store'), [
                'jabatan' => 'kepala_sekolah',
                'nama' => 'Drs. Kepala Sekolah',
                'pangkat_golongan' => 'IV/a',
                'nip' => '196501011990031001',
                'mulai_menjabat' => '2026-01-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('master_data_pegawai', [
            'sekolah_id' => $sekolah->id,
            'nama_lengkap' => 'Drs. Kepala Sekolah',
        ]);
        $this->assertDatabaseHas('master_data_jabatan', [
            'sekolah_id' => $sekolah->id,
            'kode' => 'kepala_sekolah',
        ]);
    }

    public function test_approval_gagal_menyebutkan_semua_jabatan_yang_kurang(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.semuakurang');
        $admin = $this->user($sekolah, 'admin.semuakurang', 'admin_kcd');
        $service = app(PenugasanPegawaiService::class);

        // Hanya kepala sekolah yang diisi.
        $service->simpan((string) $sekolah->id, (string) $admin->id, [
            'jabatan' => 'kepala_sekolah',
            'nama' => 'Kepala Only',
            'mulai_menjabat' => '2026-01-01',
        ]);

        try {
            $service->snapshotPenandatangan((string) $sekolah->id, '2026-06-17');
            $this->fail('Snapshot seharusnya gagal.');
        } catch (\RuntimeException $e) {
            // Kepala sekolah sudah terisi; dua jabatan lain wajib disebut.
            $this->assertStringContainsString('Wakasek Sarana Prasarana', $e->getMessage());
            $this->assertStringContainsString('Pembantu Pengurus Barang', $e->getMessage());
            $this->assertStringNotContainsString('Kepala Sekolah (', $e->getMessage());
        }
    }

    public function test_dokumen_tambahan_menolak_pengajuan_non_approved(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->user($sekolah, 'op.guard');
        $pengajuan = $this->submittedPengajuan($sekolah, $operator);
        $this->lengkapiPejabat($sekolah);

        try {
            app(DokumenService::class)->generateTambahan($pengajuan->fresh('details'), 'bast');
            $this->fail('generateTambahan pada pengajuan submitted seharusnya gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah disetujui', $e->getMessage());
        }

        $this->assertSame(0, $pengajuan->fresh()->dokumen()->count());
    }
}
