<?php

namespace Tests\Feature;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PenugasanPegawaiServiceTest extends TestCase
{
    use RefreshDatabase;

    private PenugasanPegawaiService $service;

    private Sekolah $sekolah;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PenugasanPegawaiService::class);
        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Negeri 1',
            'kota_kab' => 'Kota Bandung',
        ]));
        $this->admin = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
    }

    /** @param array<string, mixed> $override */
    private function data(array $override = []): array
    {
        return $override + [
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'pangkat_golongan' => 'IV/a',
            'jabatan' => 'kepala_sekolah',
            'jenis_penugasan' => 'definitif',
            'mulai_menjabat' => '2026-01-01',
        ];
    }

    public function test_simpan_membuat_pegawai_jabatan_dan_penugasan_sekaligus(): void
    {
        $penugasan = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());

        $this->assertSame('kepala_sekolah', $penugasan->jabatan->kode);
        $this->assertSame('Budi Santoso', $penugasan->pegawai->nama_lengkap);
        $this->assertSame('definitif', $penugasan->jenis_penugasan);
        $this->assertSame($this->admin->id, $penugasan->created_by);
        $this->assertSame(1, Jabatan::count());
        $this->assertSame(1, Pegawai::count());
    }

    public function test_simpan_ulang_memakai_pegawai_dan_jabatan_yang_sama(): void
    {
        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'jabatan' => 'wakasek_sarpras',
            'mulai_menjabat' => '2026-01-01',
        ]));

        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());

        $this->assertSame(1, Pegawai::count());
        $this->assertSame(2, Jabatan::count());
        $this->assertSame(2, PenugasanPegawai::count());
    }

    public function test_penugasan_baru_pada_jabatan_sama_menutup_yang_lama_h_minus_satu(): void
    {
        $lama = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());

        $baru = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'nama' => 'Citra Dewi',
            'nip' => '199002022010012002',
            'jenis_penugasan' => 'plt',
            'mulai_menjabat' => '2026-03-11',
        ]));

        $this->assertSame('2026-03-10', $lama->fresh()->selesai_menjabat->format('Y-m-d'));
        $this->assertNull($baru->selesai_menjabat);
        $this->assertSame('plt', $baru->jenis_penugasan);
        $this->assertSame(2, Pegawai::count());
    }

    public function test_periode_beririsan_ditolak(): void
    {
        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => '2026-06-30',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tumpang tindih');

        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'nama' => 'Citra Dewi',
            'nip' => '199002022010012002',
            'mulai_menjabat' => '2026-05-01',
            'selesai_menjabat' => '2026-12-31',
        ]));
    }

    public function test_selesai_sebelum_mulai_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sebelum tanggal mulai');

        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'mulai_menjabat' => '2026-06-01',
            'selesai_menjabat' => '2026-01-01',
        ]));
    }

    public function test_akhiri_menutup_penugasan_tanpa_menghapus_pegawai(): void
    {
        $penugasan = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());

        $hasil = $this->service->akhiri($this->sekolah->id, $this->admin->id, $penugasan, '2026-04-30');

        $this->assertSame('2026-04-30', $hasil->selesai_menjabat->format('Y-m-d'));
        $this->assertSame(1, Pegawai::count());
        $this->assertSame(0, PenugasanPegawai::aktif($this->sekolah->id, $penugasan->jabatan_id)->count());
    }

    public function test_mutasi_menutup_penugasan_lama_dan_membuka_jabatan_baru(): void
    {
        $lama = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'jabatan' => 'wakasek_sarpras',
        ]));

        $baru = $this->service->mutasi($this->sekolah->id, $this->admin->id, $lama, [
            'jabatan' => 'kepala_sekolah',
            'mulai_menjabat' => '2026-07-01',
        ]);

        $this->assertSame('2026-06-30', $lama->fresh()->selesai_menjabat->format('Y-m-d'));
        $this->assertSame('kepala_sekolah', $baru->jabatan->kode);
        $this->assertSame($lama->pegawai_id, $baru->pegawai_id);
        $this->assertSame(1, Pegawai::count());
    }

    public function test_pejabat_pada_tanggal_lampau_memakai_penugasan_yang_berlaku(): void
    {
        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'mulai_menjabat' => '2024-01-01',
            'selesai_menjabat' => '2025-03-10',
        ]));
        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
            'nama' => 'Citra Dewi',
            'nip' => '199002022010012002',
            'mulai_menjabat' => '2025-03-11',
        ]));

        $lampau = $this->service->pejabatPada($this->sekolah->id, '2025-02-01');
        $kini = $this->service->pejabatPada($this->sekolah->id, '2026-01-01');

        $this->assertSame('Budi Santoso', $lampau['kepala_sekolah']['nama']);
        $this->assertSame('Citra Dewi', $kini['kepala_sekolah']['nama']);
    }

    public function test_pejabat_pada_memakai_prioritas_definitif_lalu_plt_lalu_plh(): void
    {
        $jabatan = Jabatan::create([
            'sekolah_id' => $this->sekolah->id,
            'kode' => 'kepala_sekolah',
            'nama' => 'Kepala Sekolah',
        ]);
        $pegawai = Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Definitif']);
        $plt = Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Plt']);
        $plh = Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Plh']);

        // Ketiganya sengaja dibuat berlaku pada tanggal yang sama untuk menguji
        // prioritas; hanya dibedakan tanggal mulainya (unique per jabatan).
        foreach ([[$pegawai, 'definitif', '2026-01-01'], [$plt, 'plt', '2026-02-01'], [$plh, 'plh', '2026-03-01']] as [$orang, $jenis, $mulai]) {
            PenugasanPegawai::create([
                'sekolah_id' => $this->sekolah->id,
                'pegawai_id' => $orang->id,
                'jabatan_id' => $jabatan->id,
                'jenis_penugasan' => $jenis,
                'mulai_menjabat' => $mulai,
                'selesai_menjabat' => '2026-12-31',
            ]);
        }

        $this->assertSame('Definitif', $this->service->pejabatPada($this->sekolah->id, '2026-06-01')['kepala_sekolah']['nama']);
    }

    public function test_snapshot_penandatangan_melempar_bila_jabatan_kurang(): void
    {
        $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());

        try {
            $this->service->snapshotPenandatangan($this->sekolah->id, '2026-06-01');
            $this->fail('Snapshot seharusnya gagal.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Wakasek Sarana Prasarana', $e->getMessage());
            $this->assertStringContainsString('Pembantu Pengurus Barang', $e->getMessage());
            $this->assertStringNotContainsString('Kepala Sekolah (', $e->getMessage());
        }
    }

    public function test_snapshot_penandatangan_lengkap_mengembalikan_tiga_jabatan(): void
    {
        foreach (Jabatan::KODE_PENANDATANGAN as $i => $kode) {
            $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data([
                'nama' => 'Pejabat '.$i,
                'nip' => '19800101200'.$i,
                'jabatan' => $kode,
            ]));
        }

        $snapshot = $this->service->snapshotPenandatangan($this->sekolah->id, '2026-06-01');

        $this->assertSame(array_keys(Jabatan::LABEL_PENANDATANGAN), array_keys($snapshot));
        $this->assertSame('Pejabat 0', $snapshot['kepala_sekolah']['nama']);
    }

    public function test_sekolah_lain_tidak_bisa_diakhiri_lewat_service(): void
    {
        $penugasan = $this->service->simpan($this->sekolah->id, $this->admin->id, $this->data());
        $sekolahLain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Lain',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->expectException(HttpException::class);

        $this->service->akhiri($sekolahLain->id, $this->admin->id, $penugasan, '2026-04-30');
    }
}
