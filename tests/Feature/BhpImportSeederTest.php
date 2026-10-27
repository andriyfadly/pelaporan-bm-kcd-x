<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\HargaBarang;
use App\Models\DistribusiBhp\KodeRekening;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Penyedia;
use App\Models\Master\Sekolah;
use Database\Seeders\DistribusiBhpImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BhpImportSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath;

    private string $sekolahId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourcePath = $this->buildSourceDatabase();
        // Tidak ada pool pusat: penerimaan hasil impor harus punya sekolah.
        $this->sekolahId = (string) Sekolah::create([
            'nama_sekolah' => 'SMKN Impor BHP',
            'kota_kab' => 'Kota Bandung',
        ])->id;
    }

    /**
     * Bangun SQLite sumber dengan skema yang meniru Prisma (soft delete + cart_status).
     */
    private function buildSourceDatabase(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bhp_src_').'.sqlite';
        @unlink($path);
        touch($path);

        config()->set('database.connections.bhp_import', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $conn = DB::connection('bhp_import');

        $conn->statement('CREATE TABLE kode_rekening (id TEXT PRIMARY KEY, kode TEXT, nama TEXT, kode_barang TEXT, uraian_kode_barang TEXT, sumber_dana TEXT, deleted_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $conn->statement('CREATE TABLE barang (id TEXT PRIMARY KEY, kode_barang TEXT, nama_barang TEXT, spesifikasi_barang_nusp TEXT, satuan TEXT, kode_rekening_id TEXT, sumber_dana TEXT, deleted_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $conn->statement('CREATE TABLE harga_barang (id TEXT PRIMARY KEY, barang_id TEXT, harga_satuan INTEGER, created_by TEXT, deleted_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $conn->statement('CREATE TABLE penyedia (id TEXT PRIMARY KEY, nama TEXT, deleted_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $conn->statement('CREATE TABLE buku_penerimaan_barang (id TEXT PRIMARY KEY, no_nota_invoice TEXT, no_bast TEXT, tanggal_bast DATETIME, no_kwitansi TEXT, penyedia_id TEXT, tanggal_titimangsa DATETIME, is_saldo_awal BOOLEAN, periode_tahun INTEGER, keterangan_saldo_awal TEXT, sumber_dana TEXT, created_at DATETIME, updated_at DATETIME)');
        $conn->statement('CREATE TABLE buku_penerimaan_barang_detail (id TEXT PRIMARY KEY, buku_penerimaan_barang_id TEXT, spesifikasi_barang_jumlah INTEGER, spesifikasi_nama_barang TEXT, merek_type TEXT, harga_satuan INTEGER, nilai_total INTEGER, keterangan TEXT, barang_id TEXT, harga_barang_id TEXT, cart_status TEXT, created_at DATETIME, updated_at DATETIME)');

        $now = '2026-01-01 00:00:00';

        $conn->table('kode_rekening')->insert([
            ['id' => 'rek-bos', 'kode' => '5.1.02.01.01.0008', 'nama' => 'Bahan-Bahan', 'kode_barang' => '1.1.7.01.01', 'uraian_kode_barang' => 'Bahan', 'sumber_dana' => 'BOS', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'rek-bos-del', 'kode' => '5.1.02.01.01.9999', 'nama' => 'Dihapus', 'kode_barang' => null, 'uraian_kode_barang' => null, 'sumber_dana' => 'BOS', 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $conn->table('barang')->insert([
            ['id' => 'brg-1', 'kode_barang' => '1.1.7.01.03', 'nama_barang' => 'Kertas A4', 'spesifikasi_barang_nusp' => null, 'satuan' => 'Rim', 'kode_rekening_id' => 'rek-bos', 'sumber_dana' => 'BOS', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'brg-del', 'kode_barang' => 'X', 'nama_barang' => 'Barang Dihapus', 'spesifikasi_barang_nusp' => null, 'satuan' => 'Unit', 'kode_rekening_id' => null, 'sumber_dana' => 'BOS', 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $conn->table('harga_barang')->insert([
            ['id' => 'hrg-1', 'barang_id' => 'brg-1', 'harga_satuan' => 100000, 'created_by' => null, 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $conn->table('penyedia')->insert([
            ['id' => 'pny-1', 'nama' => 'CV Sumber Rejeki', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'pny-del', 'nama' => 'Toko Lama', 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $conn->table('buku_penerimaan_barang')->insert([
            ['id' => 'terima-1', 'no_nota_invoice' => 'INV-1', 'no_bast' => 'BAST/001', 'tanggal_bast' => '2026-02-19 00:00:00', 'no_kwitansi' => 'KW-1', 'penyedia_id' => 'pny-1', 'tanggal_titimangsa' => '2026-02-20 00:00:00', 'is_saldo_awal' => 0, 'periode_tahun' => null, 'keterangan_saldo_awal' => null, 'sumber_dana' => 'BOS', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $conn->table('buku_penerimaan_barang_detail')->insert([
            ['id' => 'tdet-1', 'buku_penerimaan_barang_id' => 'terima-1', 'spesifikasi_barang_jumlah' => 20, 'spesifikasi_nama_barang' => 'Warna Putih', 'merek_type' => 'SIDU', 'harga_satuan' => 100000, 'nilai_total' => 2000000, 'keterangan' => 'Baik', 'barang_id' => 'brg-1', 'harga_barang_id' => 'hrg-1', 'cart_status' => 'saved', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'tdet-cart', 'buku_penerimaan_barang_id' => 'terima-1', 'spesifikasi_barang_jumlah' => 99, 'spesifikasi_nama_barang' => 'Masih Keranjang', 'merek_type' => null, 'harga_satuan' => 100000, 'nilai_total' => 9900000, 'keterangan' => null, 'barang_id' => 'brg-1', 'harga_barang_id' => 'hrg-1', 'cart_status' => 'in_cart', 'created_at' => $now, 'updated_at' => $now],
        ]);

        return $path;
    }

    private function runSeeder(): void
    {
        (new DistribusiBhpImportSeeder)->setConnection('bhp_import')->run();
    }

    public function test_mengimpor_master_data_dan_melewati_soft_delete(): void
    {
        $this->runSeeder();

        $this->assertSame(1, KodeRekening::count());
        $this->assertTrue(KodeRekening::where('kode', '5.1.02.01.01.0008')->exists());
        $this->assertFalse(KodeRekening::where('kode', '5.1.02.01.01.9999')->exists());

        $this->assertSame(1, Barang::count());
        $this->assertSame(1, HargaBarang::count());
        $this->assertSame(1, Penyedia::count());
        $this->assertFalse(Penyedia::where('nama', 'Toko Lama')->exists());
    }

    public function test_menjaga_relasi_barang_ke_kode_rekening(): void
    {
        $this->runSeeder();

        $barang = Barang::firstOrFail();
        $this->assertSame('Kertas A4', $barang->nama_barang);
        $this->assertSame('Rim', $barang->satuan);
        $this->assertNotNull($barang->kode_rekening_id);
        $this->assertSame('5.1.02.01.01.0008', $barang->kodeRekening?->kode);
    }

    public function test_mengimpor_penerimaan_dan_detail_yang_sah(): void
    {
        $this->runSeeder();

        $this->assertSame(1, Penerimaan::count());
        $terima = Penerimaan::firstOrFail();
        $this->assertSame($this->sekolahId, (string) $terima->sekolah_id);
        $this->assertSame('BAST/001', $terima->no_bast);
        $this->assertSame('2026-02-19', $terima->tanggal_bast->toDateString());
        $this->assertNotNull($terima->penyedia_id);

        // Hanya detail cart_status=saved yang diimpor.
        $this->assertSame(1, PenerimaanDetail::count());
        $detail = PenerimaanDetail::firstOrFail();
        $this->assertSame(20, $detail->jumlah);
        $this->assertSame(100000, $detail->harga_satuan);
        $this->assertSame(2000000, $detail->nilai_total);
    }

    public function test_stok_fisik_menggunakan_jumlah_hasil_impor(): void
    {
        $this->runSeeder();

        $barang = Barang::firstOrFail();
        $this->assertSame(20, (int) PenerimaanDetail::where('barang_id', $barang->id)->sum('jumlah'));
    }

    public function test_seeder_idempotent(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(1, KodeRekening::count());
        $this->assertSame(1, Barang::count());
        $this->assertSame(1, Penyedia::count());
        $this->assertSame(1, Penerimaan::count());
        $this->assertSame(1, PenerimaanDetail::count());
    }

    public function test_seeder_melewati_bila_tabel_sumber_tidak_ada(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bhp_empty_').'.sqlite';
        @unlink($path);
        touch($path);
        config()->set('database.connections.bhp_import_empty', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        (new DistribusiBhpImportSeeder)->setConnection('bhp_import_empty')->run();

        $this->assertSame(0, KodeRekening::count());
        $this->assertSame(0, Barang::count());
    }

    public function test_menghapus_tabel_sementara_tidak_dilakukan(): void
    {
        // Regresi: koneksi sumber tidak boleh menyentuh tabel domain BHP.
        $this->runSeeder();

        $this->assertTrue(Schema::hasTable('bhp_bos_kode_rekening'));
        $this->assertTrue(Schema::hasTable('bhp_bos_barang'));
    }

    public function test_penerimaan_dengan_no_bast_duplikat_tetap_terpisah(): void
    {
        // Sumber nyata punya 12 baris no_bast NULL dan 2 baris "BAST" yang sama;
        // natural key harus memakai source_id, bukan no_bast.
        $conn = DB::connection('bhp_import');
        $conn->table('buku_penerimaan_barang')->insert([
            ['id' => 'terima-2', 'no_nota_invoice' => null, 'no_bast' => null, 'tanggal_bast' => null, 'no_kwitansi' => null, 'penyedia_id' => null, 'tanggal_titimangsa' => null, 'is_saldo_awal' => 1, 'periode_tahun' => 2026, 'keterangan_saldo_awal' => 'Saldo Awal', 'sumber_dana' => 'BOS', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 'terima-3', 'no_nota_invoice' => null, 'no_bast' => null, 'tanggal_bast' => null, 'no_kwitansi' => null, 'penyedia_id' => null, 'tanggal_titimangsa' => null, 'is_saldo_awal' => 1, 'periode_tahun' => 2027, 'keterangan_saldo_awal' => 'Saldo Awal', 'sumber_dana' => 'BOS', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);

        $this->runSeeder();

        $this->assertSame(3, Penerimaan::count());
        $this->assertSame('terima-1', Penerimaan::where('no_bast', 'BAST/001')->value('source_id'));
        $this->assertSame(2, Penerimaan::where('is_saldo_awal', true)->count());
        $this->assertSame(['terima-2', 'terima-3'], Penerimaan::where('is_saldo_awal', true)->orderBy('source_id')->pluck('source_id')->all());
    }

    public function test_seeder_mengisi_source_id_penerimaan(): void
    {
        $this->runSeeder();

        $this->assertSame('terima-1', Penerimaan::firstOrFail()->source_id);
    }

    protected function tearDown(): void
    {
        @unlink($this->sourcePath);
        parent::tearDown();
    }
}
