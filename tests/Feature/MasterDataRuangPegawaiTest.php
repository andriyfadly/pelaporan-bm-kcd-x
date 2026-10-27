<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataRuangPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private function sekolah(): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Negeri 1',
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    public function test_tabel_master_ruang_dan_pegawai_tersedia_dan_tabel_lama_hilang(): void
    {
        $this->assertTrue(Schema::hasTable('master_data_ruang'));
        $this->assertTrue(Schema::hasTable('master_data_pegawai'));
        $this->assertFalse(Schema::hasTable('inventaris_ruang'));
        $this->assertFalse(Schema::hasTable('inventaris_pegawai'));

        foreach (['sekolah_id', 'nama_ruang', 'kode', 'aktif'] as $kolom) {
            $this->assertTrue(Schema::hasColumn('master_data_ruang', $kolom), "Kolom {$kolom} tidak ada.");
        }

        foreach (['sekolah_id', 'nama_lengkap', 'nip', 'pangkat_golongan', 'aktif'] as $kolom) {
            $this->assertTrue(Schema::hasColumn('master_data_pegawai', $kolom), "Kolom {$kolom} tidak ada.");
        }
    }

    public function test_model_master_memakai_tabel_baru_dan_relasi_unit(): void
    {
        $this->assertSame('master_data_ruang', (new Ruang)->getTable());
        $this->assertSame('master_data_pegawai', (new Pegawai)->getTable());

        $sekolah = $this->sekolah();
        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
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

        $ruang = Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'Ruang Guru']);
        $pegawai = Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Budi']);

        InventarisUnit::create([
            'sekolah_id' => $sekolah->id,
            'realisasi_id' => $realisasi->id,
            'unit_index' => 1,
            'kode_barang' => $realisasi->kode_barang,
            'nama_barang' => $realisasi->nama_barang,
            'nomor_register' => '001',
            'ruang_id' => $ruang->id,
            'pegawai_id' => $pegawai->id,
        ]);

        $this->assertSame(1, $ruang->unitAktif()->count());
        $this->assertSame(1, $pegawai->unitAktif()->count());
        $this->assertTrue($ruang->sekolah->is($sekolah));
        $this->assertTrue($pegawai->sekolah->is($sekolah));
    }

    public function test_nama_ruang_tetap_unik_per_sekolah_tanpa_peka_huruf(): void
    {
        $sekolah = $this->sekolah();

        Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'Lab Komputer']);

        $this->expectException(UniqueConstraintViolationException::class);

        Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'lab komputer']);
    }
}
