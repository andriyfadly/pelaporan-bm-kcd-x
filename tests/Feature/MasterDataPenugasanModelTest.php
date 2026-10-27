<?php

namespace Tests\Feature;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\PenugasanPegawai;
use App\Models\Master\Sekolah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataPenugasanModelTest extends TestCase
{
    use RefreshDatabase;

    private function sekolah(string $nama = 'SMA Negeri 1'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function jabatan(Sekolah $sekolah, string $kode = 'kepala_sekolah', string $nama = 'Kepala Sekolah'): Jabatan
    {
        return Jabatan::create([
            'sekolah_id' => $sekolah->id,
            'kode' => $kode,
            'nama' => $nama,
        ]);
    }

    private function pegawai(Sekolah $sekolah, string $nama = 'Budi Santoso'): Pegawai
    {
        return Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => $nama]);
    }

    private function penugasan(Sekolah $sekolah, Pegawai $pegawai, Jabatan $jabatan, array $override = []): PenugasanPegawai
    {
        return PenugasanPegawai::create($override + [
            'sekolah_id' => $sekolah->id,
            'pegawai_id' => $pegawai->id,
            'jabatan_id' => $jabatan->id,
            'jenis_penugasan' => 'definitif',
            'mulai_menjabat' => '2026-01-01',
        ]);
    }

    public function test_skema_jabatan_dan_penugasan_lengkap(): void
    {
        foreach (['master_data_jabatan', 'master_data_penugasan_pegawai'] as $tabel) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");
        }

        foreach (['sekolah_id', 'kode', 'nama', 'kategori', 'aktif'] as $kolom) {
            $this->assertTrue(Schema::hasColumn('master_data_jabatan', $kolom), "Kolom {$kolom} tidak ada.");
        }

        foreach ([
            'sekolah_id', 'pegawai_id', 'jabatan_id', 'jenis_penugasan',
            'mulai_menjabat', 'selesai_menjabat', 'created_by', 'updated_by',
        ] as $kolom) {
            $this->assertTrue(Schema::hasColumn('master_data_penugasan_pegawai', $kolom), "Kolom {$kolom} tidak ada.");
        }
    }

    public function test_kode_jabatan_unik_per_sekolah(): void
    {
        $sekolah = $this->sekolah();
        $this->jabatan($sekolah);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->jabatan($sekolah, 'kepala_sekolah', 'Kepala Sekolah Lain');
    }

    public function test_sekolah_berbeda_boleh_memakai_kode_jabatan_sama(): void
    {
        $this->jabatan($this->sekolah('SMA A'));
        $this->jabatan($this->sekolah('SMA B'));

        $this->assertSame(2, Jabatan::count());
    }

    public function test_relasi_penugasan_ke_pegawai_dan_jabatan(): void
    {
        $sekolah = $this->sekolah();
        $pegawai = $this->pegawai($sekolah);
        $jabatan = $this->jabatan($sekolah);

        $penugasan = $this->penugasan($sekolah, $pegawai, $jabatan, [
            'jenis_penugasan' => 'plt',
            'selesai_menjabat' => '2026-06-30',
        ]);

        $this->assertTrue($penugasan->pegawai->is($pegawai));
        $this->assertTrue($penugasan->jabatan->is($jabatan));
        $this->assertTrue($penugasan->sekolah->is($sekolah));
        $this->assertSame('2026-01-01', $penugasan->mulai_menjabat->format('Y-m-d'));
        $this->assertSame('2026-06-30', $penugasan->selesai_menjabat->format('Y-m-d'));
        $this->assertSame(1, $pegawai->penugasan()->count());
        $this->assertSame(1, $jabatan->penugasan()->count());
    }

    public function test_satu_jabatan_hanya_boleh_satu_penugasan_terbuka(): void
    {
        $sekolah = $this->sekolah();
        $jabatan = $this->jabatan($sekolah);

        $this->penugasan($sekolah, $this->pegawai($sekolah, 'Budi'), $jabatan, ['mulai_menjabat' => '2026-01-01']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->penugasan($sekolah, $this->pegawai($sekolah, 'Citra'), $jabatan, ['mulai_menjabat' => '2026-03-01']);
    }

    public function test_penugasan_tertutup_boleh_menumpuk_pada_jabatan_sama(): void
    {
        $sekolah = $this->sekolah();
        $jabatan = $this->jabatan($sekolah);

        $this->penugasan($sekolah, $this->pegawai($sekolah, 'Budi'), $jabatan, [
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => '2026-02-28',
        ]);

        $kedua = $this->penugasan($sekolah, $this->pegawai($sekolah, 'Citra'), $jabatan, [
            'mulai_menjabat' => '2026-03-01',
        ]);

        $this->assertSame(2, PenugasanPegawai::count());
        $this->assertTrue(PenugasanPegawai::aktif($sekolah->id, $jabatan->id)->get()->contains($kedua));
    }

    public function test_scope_berlaku_pada_mencari_pejabat_tanggal_lampau(): void
    {
        $sekolah = $this->sekolah();
        $jabatan = $this->jabatan($sekolah);

        $lama = $this->penugasan($sekolah, $this->pegawai($sekolah, 'Budi'), $jabatan, [
            'mulai_menjabat' => '2024-01-01',
            'selesai_menjabat' => '2025-03-10',
        ]);

        $baru = $this->penugasan($sekolah, $this->pegawai($sekolah, 'Citra'), $jabatan, [
            'mulai_menjabat' => '2025-03-11',
        ]);

        $this->assertTrue(PenugasanPegawai::berlakuPada($sekolah->id, $jabatan->id, '2025-02-01')->first()->is($lama));
        $this->assertTrue(PenugasanPegawai::berlakuPada($sekolah->id, $jabatan->id, '2025-03-10')->first()->is($lama));
        $this->assertTrue(PenugasanPegawai::berlakuPada($sekolah->id, $jabatan->id, '2025-03-11')->first()->is($baru));
        $this->assertNull(PenugasanPegawai::berlakuPada($sekolah->id, $jabatan->id, '2023-12-31')->first());
    }

    public function test_jabatan_tersedia_memfilter_aktif_dan_sekolah(): void
    {
        $sekolah = $this->sekolah();
        $lain = $this->sekolah('SMA Lain');

        $aktif = $this->jabatan($sekolah, 'kepala_sekolah', 'Kepala Sekolah');
        $nonaktif = $this->jabatan($sekolah, 'operator', 'Operator');
        $nonaktif->update(['aktif' => false]);
        $this->jabatan($lain, 'kepala_sekolah', 'Kepala Lain');

        $hasil = Jabatan::tersedia($sekolah->id)->get();

        $this->assertCount(1, $hasil);
        $this->assertTrue($hasil->first()->is($aktif));
    }

    public function test_prioritas_jenis_mengurutkan_definitif_plt_plh(): void
    {
        $this->assertSame(0, PenugasanPegawai::prioritasJenis('definitif'));
        $this->assertSame(1, PenugasanPegawai::prioritasJenis('plt'));
        $this->assertSame(2, PenugasanPegawai::prioritasJenis('plh'));
        $this->assertSame(99, PenugasanPegawai::prioritasJenis('lain'));
    }

    public function test_snapshot_menyimpan_nama_dan_label_jabatan(): void
    {
        $sekolah = $this->sekolah();
        $jabatan = $this->jabatan($sekolah);
        $pegawai = $this->pegawai($sekolah);
        $pegawai->update(['nip' => '198001012005011001', 'pangkat_golongan' => 'IV/a']);

        $penugasan = $this->penugasan($sekolah, $pegawai, $jabatan, ['jenis_penugasan' => 'plt']);

        $this->assertSame('Plt. Kepala Sekolah', $penugasan->labelJabatan());
        $this->assertSame([
            'pegawai_id' => $pegawai->id,
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'pangkat_golongan' => 'IV/a',
            'jabatan_id' => $jabatan->id,
            'jabatan' => 'Plt. Kepala Sekolah',
            'jenis_penugasan' => 'plt',
            'mulai_menjabat' => '2026-01-01',
            'selesai_menjabat' => null,
        ], $penugasan->toSnapshot());
    }
}
