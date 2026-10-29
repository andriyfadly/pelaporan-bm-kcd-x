<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventarisModelTest extends TestCase
{
    use RefreshDatabase;

    private function sekolah(string $nama = 'SMA Negeri 1'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function realisasi(Sekolah $sekolah, float $volume = 2): Realisasi
    {
        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'UNIT',
            'volume' => $volume,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => $volume * 9000000,
        ]));
    }

    private function unit(Sekolah $sekolah, Realisasi $realisasi, array $override = []): InventarisUnit
    {
        return InventarisUnit::create($override + [
            'sekolah_id' => $sekolah->id,
            'realisasi_id' => $realisasi->id,
            'kode_barang' => $realisasi->kode_barang,
            'nama_barang' => $realisasi->nama_barang,
            'unit_index' => 1,
            'nomor_register' => '001',
            'harga_satuan' => 9000000,
            'nilai_perolehan_unit' => 9000000,
        ]);
    }

    public function test_skema_inventaris_lengkap_dengan_kolom_wajib(): void
    {
        foreach ([
            'inventaris_unit', 'inventaris_register_sequences', 'master_data_ruang',
            'master_data_pegawai', 'inventaris_mutasi', 'inventaris_dokumen',
            'inventaris_foto', 'inventaris_sync_logs',
        ] as $tabel) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");
        }

        foreach ([
            'sekolah_id', 'realisasi_id', 'kode_barang', 'nomor_register', 'unit_index',
            'kondisi', 'status', 'ruang_id', 'pegawai_id', 'foto_path', 'keterangan',
            'tanggal_perolehan', 'bulan_perolehan', 'tahun_perolehan',
            'harga_satuan', 'nilai_perolehan_unit',
        ] as $kolom) {
            $this->assertTrue(Schema::hasColumn('inventaris_unit', $kolom), "Kolom {$kolom} tidak ada.");
        }
    }

    public function test_register_unik_per_sekolah_kode_barang_dan_tahun(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        $this->unit($sekolah, $realisasi, ['unit_index' => 1, 'nomor_register' => '001', 'tahun_perolehan' => 2026]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->unit($sekolah, $realisasi, ['unit_index' => 2, 'nomor_register' => '001', 'tahun_perolehan' => 2026]);
    }

    public function test_register_boleh_sama_di_tahun_berbeda(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        $this->unit($sekolah, $realisasi, ['unit_index' => 1, 'nomor_register' => '001', 'tahun_perolehan' => 2025]);
        $this->unit($sekolah, $realisasi, ['unit_index' => 2, 'nomor_register' => '001', 'tahun_perolehan' => 2026]);

        $this->assertSame(2, InventarisUnit::count());
    }

    public function test_tahun_perolehan_tidak_boleh_kosong_karena_menentukan_register(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        // NULL di unique index dianggap berbeda-beda, jadi dua '001' tanpa
        // tahun akan lolos. Model mengisinya otomatis dari tanggal perolehan.
        $unit = $this->unit($sekolah, $realisasi, [
            'unit_index' => 1,
            'nomor_register' => '001',
            'tanggal_perolehan' => '2025-07-01',
            'tahun_perolehan' => null,
        ]);

        $this->assertSame(2025, $unit->tahun_perolehan);

        $tanpaTanggal = $this->unit($sekolah, $realisasi, [
            'unit_index' => 2,
            'nomor_register' => '001',
            'tahun_perolehan' => null,
        ]);

        $this->assertSame((int) now()->year, $tanpaTanggal->tahun_perolehan);
    }

    public function test_kolom_tahun_perolehan_wajib_di_database(): void
    {
        $kolom = collect(Schema::getColumns('inventaris_unit'))->firstWhere('name', 'tahun_perolehan');

        $this->assertFalse(
            $kolom['nullable'],
            'tahun_perolehan harus NOT NULL: NULL membuat unique register tidak berlaku.',
        );
    }

    public function test_sekolah_berbeda_boleh_memakai_register_sama(): void
    {
        $sekolahA = $this->sekolah('SMA A');
        $sekolahB = $this->sekolah('SMA B');

        $this->unit($sekolahA, $this->realisasi($sekolahA), ['nomor_register' => '001']);
        $this->unit($sekolahB, $this->realisasi($sekolahB), ['nomor_register' => '001']);

        $this->assertSame(2, InventarisUnit::count());
    }

    public function test_unit_index_unik_per_realisasi(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        $this->unit($sekolah, $realisasi, ['unit_index' => 1, 'nomor_register' => '001']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->unit($sekolah, $realisasi, ['unit_index' => 1, 'nomor_register' => '002']);
    }

    public function test_relasi_dan_cast_unit(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        $ruang = Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'Ruang Guru']);
        $pegawai = Pegawai::create(['sekolah_id' => $sekolah->id, 'nama_lengkap' => 'Budi']);

        $unit = $this->unit($sekolah, $realisasi, [
            'ruang_id' => $ruang->id,
            'pegawai_id' => $pegawai->id,
            'kondisi' => 'RR',
            'harga_satuan' => 9000000.5,
        ]);

        $this->assertSame('RR', $unit->kondisi);
        $this->assertSame('aktif', $unit->status);
        $this->assertSame('9000000.50', $unit->harga_satuan);
        $this->assertTrue($unit->sekolah->is($sekolah));
        $this->assertTrue($unit->realisasi->is($realisasi));
        $this->assertTrue($unit->ruang->is($ruang));
        $this->assertTrue($unit->pegawai->is($pegawai));
        $this->assertSame(1, $unit->unit_index);
    }

    public function test_mutasi_menyimpan_histori_dan_relasi_ke_unit(): void
    {
        $sekolah = $this->sekolah();
        $unit = $this->unit($sekolah, $this->realisasi($sekolah));

        $mutasi = Mutasi::create([
            'sekolah_id' => $sekolah->id,
            'inventaris_unit_id' => $unit->id,
            'jenis' => 'penyaluran',
            'tujuan_kondisi' => 'B',
            'tanggal' => '2026-03-10',
        ]);

        $this->assertSame('penyaluran', $mutasi->jenis);
        $this->assertSame('2026-03-10', $mutasi->tanggal->format('Y-m-d'));
        $this->assertSame(1, $unit->mutasi()->count());
        $this->assertSame($sekolah->id, $mutasi->sekolah_id);
    }

    public function test_foto_unit_menyimpan_metadata_berkas(): void
    {
        $sekolah = $this->sekolah();
        $unit = $this->unit($sekolah, $this->realisasi($sekolah));
        $user = User::factory()->create();

        $foto = Foto::create([
            'sekolah_id' => $sekolah->id,
            'inventaris_unit_id' => $unit->id,
            'path' => 'inventaris/unit/foto.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'checksum' => str_repeat('a', 64),
            'uploaded_by' => $user->id,
        ]);

        $this->assertSame($unit->id, $foto->unit->id);
        $this->assertSame(1024, $foto->size);
    }

    public function test_ruang_memakai_soft_delete_dan_nama_unik_case_insensitive(): void
    {
        $sekolah = $this->sekolah();
        $ruang = Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'Lab Komputer']);

        $ruang->delete();

        $this->assertSoftDeleted('master_data_ruang', ['id' => $ruang->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Ruang::create(['sekolah_id' => $sekolah->id, 'nama_ruang' => 'lab komputer']);
    }
}
