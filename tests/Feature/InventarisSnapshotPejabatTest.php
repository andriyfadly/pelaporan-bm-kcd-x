<?php

namespace Tests\Feature;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use App\Services\PencatatanBm\DokumenInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventarisSnapshotPejabatTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Negeri 1',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.snapshot',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');
    }

    /** @param array<string, mixed> $override */
    private function penugasan(array $override = []): void
    {
        $jabatan = $override['jabatan'] ?? 'kepala_sekolah';
        unset($override['jabatan']);

        $nama = $override['nama'] ?? 'Pejabat '.$jabatan;

        app(PenugasanPegawaiService::class)->simpan($this->sekolah->id, $this->operator->id, $override + [
            'jabatan' => $jabatan,
            'nama' => $nama,
            // NIP harus unik per orang; kalau tidak, service menggabungkan
            // dua pejabat berbeda menjadi satu pegawai.
            'nip' => '1980'.substr(md5($nama), 0, 12),
            'mulai_menjabat' => '2024-01-01',
        ]);
    }

    private function unit(): InventarisUnit
    {
        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'UNIT',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        return InventarisUnit::create([
            'sekolah_id' => $this->sekolah->id,
            'realisasi_id' => $realisasi->id,
            'unit_index' => 1,
            'kode_barang' => $realisasi->kode_barang,
            'nama_barang' => $realisasi->nama_barang,
            'nomor_register' => '001',
        ]);
    }

    public function test_dokumen_lampau_memakai_pejabat_yang_menjabat_saat_itu(): void
    {
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $this->penugasan([
                'jabatan' => $kode,
                'nama' => 'Pejabat 2024 '.$kode,
                'mulai_menjabat' => '2024-01-01',
                'selesai_menjabat' => '2024-12-31',
            ]);
            $this->penugasan([
                'jabatan' => $kode,
                'nama' => 'Pejabat 2026 '.$kode,
                'mulai_menjabat' => '2026-01-01',
            ]);
        }

        $snapshot = app(DokumenInventarisService::class)->pejabat($this->sekolah->id, '2024-06-17');

        $this->assertSame('Pejabat 2024 kepala_sekolah', $snapshot['kepala_sekolah']['nama']);
        $this->assertSame('2024-12-31', $snapshot['kepala_sekolah']['selesai_menjabat']);
    }

    public function test_dokumen_baru_memakai_pejabat_aktif(): void
    {
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $this->penugasan([
                'jabatan' => $kode,
                'nama' => 'Pejabat Lama '.$kode,
                'mulai_menjabat' => '2024-01-01',
                'selesai_menjabat' => '2024-12-31',
            ]);
            $this->penugasan([
                'jabatan' => $kode,
                'nama' => 'Pejabat Baru '.$kode,
                'mulai_menjabat' => '2025-01-01',
            ]);
        }

        $snapshot = app(DokumenInventarisService::class)->pejabat($this->sekolah->id, '2026-06-01');

        $this->assertSame('Pejabat Baru kepala_sekolah', $snapshot['kepala_sekolah']['nama']);
    }

    public function test_snapshot_tersimpan_tidak_berubah_setelah_master_diedit(): void
    {
        foreach (Jabatan::KODE_PENANDATANGAN as $kode) {
            $this->penugasan(['jabatan' => $kode, 'nama' => 'Pejabat Awal '.$kode]);
        }

        $unit = $this->unit();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $unit), ['tanggal' => '2026-03-05'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dokumen = Dokumen::firstOrFail();
        $namaAwal = $dokumen->payload['pejabat']['kepala_sekolah']['nama'];

        // Master berubah: nama pegawai diperbaiki dan pejabat diganti.
        Pegawai::where('nama_lengkap', $namaAwal)->firstOrFail()->update(['nama_lengkap' => 'Nama Diperbaiki']);
        $this->penugasan(['jabatan' => 'kepala_sekolah', 'nama' => 'Kepala Pengganti', 'mulai_menjabat' => '2026-06-01']);

        $this->assertSame($namaAwal, $dokumen->fresh()->payload['pejabat']['kepala_sekolah']['nama']);
    }

    public function test_tanpa_pejabat_dokumen_tetap_dibuat_dengan_snapshot_kosong(): void
    {
        $unit = $this->unit();
        Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $unit), ['tanggal' => '2026-03-05'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dokumen = Dokumen::firstOrFail();

        $this->assertNull($dokumen->payload['pejabat']['kepala_sekolah']);
        $this->assertNull($dokumen->payload['pejabat']['wakasek_sarpras']);
    }

    public function test_fakta_integritas_memakai_jabatan_pada_tanggal_dokumen(): void
    {
        $pegawai = Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
            'nip' => '198001012005011001',
        ]);

        app(PenugasanPegawaiService::class)->simpan($this->sekolah->id, $this->operator->id, [
            'jabatan' => 'pembantu_pengurus_barang',
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'jenis_penugasan' => 'plt',
            'mulai_menjabat' => '2026-01-01',
        ]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.fakta-integritas', $pegawai), ['tanggal' => '2026-03-05'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dokumen = Dokumen::firstOrFail();

        $this->assertSame('Plt. Pembantu Pengurus Barang', $dokumen->payload['pegawai']['jabatan']);
        $this->assertSame('plt', $dokumen->payload['pegawai']['jenis_penugasan']);
    }

    public function test_kolom_jabatan_dan_ruang_pada_master_pegawai_sudah_dihapus(): void
    {
        $this->assertFalse(Schema::hasColumn('master_data_pegawai', 'jabatan'));
        $this->assertFalse(Schema::hasColumn('master_data_pegawai', 'ruang'));
    }
}
