<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Pendaftaran unit inventaris di luar realisasi (input manual + impor Excel),
 * menyamai alur import_barang.php pada aplikasi acuan.
 */
class InventarisPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Daftar',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Daftar',
            'username' => 'op_daftar',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '1.3.2.10.01.02.002',
            'uraian' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'Unit',
        ]));
    }

    public function test_tambah_manual_membuat_satu_unit_per_volume(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'nama_barang' => 'Laptop Lenovo',
                'merk_tipe' => 'Lenovo',
                'volume' => 3,
                'harga_satuan' => 9000000,
                'asal_perolehan' => 'BOSP',
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $units = InventarisUnit::orderBy('nomor_register')->get();

        $this->assertCount(3, $units);
        $this->assertSame(['001', '002', '003'], $units->pluck('nomor_register')->all());
        $this->assertSame($this->sekolah->id, $units[0]->sekolah_id);
        $this->assertNull($units[0]->realisasi_id);
        $this->assertSame('BOSP', $units[0]->asal_perolehan);
        $this->assertSame(2, $units[0]->bulan_perolehan);
        $this->assertSame(2026, $units[0]->tahun_perolehan);
        $this->assertSame('B', $units[0]->kondisi);
    }

    public function test_register_melanjutkan_nomor_yang_sudah_terbit(): void
    {
        InventarisRegisterSequence::create([
            'sekolah_id' => $this->sekolah->id,
            'kode_barang' => '1.3.2.10.01.02.002',
            'tahun' => 2026,
            'next_number' => 5,
        ]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 2,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['005', '006'],
            InventarisUnit::orderBy('nomor_register')->pluck('nomor_register')->all(),
        );
    }

    public function test_register_diulang_dari_001_untuk_tahun_berbeda(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 2,
                'tanggal_perolehan' => '2025-05-10',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 2,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $perTahun = InventarisUnit::orderBy('tahun_perolehan')
            ->orderBy('nomor_register')
            ->get()
            ->groupBy('tahun_perolehan')
            ->map(fn ($rows) => $rows->pluck('nomor_register')->all());

        $this->assertSame([2025 => ['001', '002'], 2026 => ['001', '002']], $perTahun->all());
    }

    public function test_tahun_sama_melanjutkan_nomor_tahun_itu_saja(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
                'tanggal_perolehan' => '2025-05-10',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
                'tanggal_perolehan' => '2025-11-20',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['001', '002'],
            InventarisUnit::orderBy('nomor_register')->pluck('nomor_register')->all(),
        );
        $this->assertSame(1, InventarisRegisterSequence::where('tahun', 2025)->count());
    }

    public function test_nama_dan_satuan_kosong_memakai_master_kode_barang(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $unit = InventarisUnit::firstOrFail();

        $this->assertSame('Laptop', $unit->nama_barang);
        $this->assertSame('Unit', $unit->satuan);
        $this->assertSame('Peralatan dan Mesin', $unit->jenis_aset);
    }

    public function test_kode_di_luar_katalog_tetap_boleh_didaftarkan(): void
    {
        // Aplikasi acuan menerima kode bebas; katalog hanya untuk nilai default.
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '9.9.9.99.99',
                'nama_barang' => 'Barang Baru',
                'volume' => 1,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $unit = InventarisUnit::firstOrFail();

        $this->assertSame('9.9.9.99.99', $unit->kode_barang);
        $this->assertSame('Barang Baru', $unit->nama_barang);
    }

    public function test_validasi_volume_dan_kode(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), ['volume' => 1])
            ->assertSessionHasErrors('kode_barang');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 0,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertSessionHasErrors('volume');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
            ])
            ->assertSessionHasErrors('tanggal_perolehan');

        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_impor_memakai_urutan_kolom_acuan(): void
    {
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang,Merek / Type,Bahan,Tanggal,Bulan Perolehan,Tahun Perolehan,Satuan,Volume,Harga Satuan\n"
            ."1.3.2.10.01.02.002,BOSP,Laptop,Lenovo,Logam,10,2,2026,Unit,2,9000000\n"
            ."1.3.2.10.01.02.002,APBN,Laptop,HP,Plastik,15,3,2026,Unit,1,8000000\n";

        $file = UploadedFile::fake()->createWithContent('inventaris.csv', $csv);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, InventarisUnit::count());
        $this->assertSame(
            ['001', '002', '003'],
            InventarisUnit::orderBy('nomor_register')->pluck('nomor_register')->all(),
        );
        $unit = InventarisUnit::orderBy('nomor_register')->firstOrFail();
        $this->assertSame('1.3.2.10.01.02.002', $unit->kode_barang);
        $this->assertSame(2, $unit->bulan_perolehan);
        $this->assertSame('BOSP', $unit->asal_perolehan);
        $this->assertSame('Logam', $unit->bahan);
    }

    public function test_impor_tanpa_kondisi_menyimpan_strip_dan_masuk_agregat_baik(): void
    {
        // Acuan menyimpan keadaan_barang '-' untuk hasil impor, tetapi
        // dashboard menghitungnya sebagai sisa volume "Baik".
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang,Merek / Type,Bahan,Tanggal,Bulan Perolehan,Tahun Perolehan,Satuan,Volume,Harga Satuan\n"
            ."1.3.2.10.01.02.002,BOSP,Laptop,Lenovo,Logam,10,2,2026,Unit,1,9000000\n";

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), [
                'file' => UploadedFile::fake()->createWithContent('inventaris.csv', $csv),
            ])
            ->assertRedirect();

        $unit = InventarisUnit::firstOrFail();
        $this->assertSame(InventarisUnit::KONDISI_BELUM_DISET, $unit->kondisi);

        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.statistik'))
            ->assertOk()
            ->assertJsonPath('ringkasan.kondisi.B', 1)
            ->assertJsonPath('ringkasan.totalUnit', 1);
    }

    public function test_input_manual_tetap_memilih_kondisi_baik(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
                'tanggal_perolehan' => '2026-02-10',
            ])
            ->assertRedirect();

        $this->assertSame('B', InventarisUnit::firstOrFail()->kondisi);
    }

    public function test_impor_melewati_baris_tanpa_kode(): void
    {
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang,Merek / Type,Bahan,Tanggal,Bulan Perolehan,Tahun Perolehan,Satuan,Volume,Harga Satuan\n"
            .",BOSP,Tanpa Kode,,,10,2,2026,Unit,1,0\n"
            ."1.3.2.10.01.02.002,BOSP,Laptop,,,10,2,2026,Unit,1,0\n";

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), [
                'file' => UploadedFile::fake()->createWithContent('inventaris.csv', $csv),
            ])
            ->assertRedirect();

        $this->assertSame(1, InventarisUnit::count());
    }

    public function test_impor_gagal_di_tengah_membatalkan_seluruh_berkas(): void
    {
        // Baris pertama valid, baris kedua tanggalnya tidak bisa di-parse:
        // seluruh impor harus batal supaya register tidak terbit percuma.
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang,Merek / Type,Bahan,Tanggal,Bulan Perolehan,Tahun Perolehan,Satuan,Volume,Harga Satuan\n"
            ."1.3.2.10.01.02.002,BOSP,Laptop,Lenovo,Logam,10,2,2026,Unit,1,9000000\n"
            ."1.3.2.10.01.02.002,BOSP,Kursi,Lokal,Kayu,bukan-tanggal,2,2026,Unit,1,200000\n";

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), [
                'file' => UploadedFile::fake()->createWithContent('gagal.csv', $csv),
            ])
            ->assertSessionHasErrors('baris.3.tanggal_perolehan');

        $this->assertSame(0, InventarisUnit::count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nilaiImporTidakValid(): array
    {
        // header + satu baris data; kolom J (volume) dan K (harga) diuji.
        return [
            'volume nol' => ['1.3.2.10.01.02.002,BOSP,Laptop,,,10,2,2026,Unit,0,0', 'baris.2.volume'],
            'volume negatif' => ['1.3.2.10.01.02.002,BOSP,Laptop,,,10,2,2026,Unit,-2,0', 'baris.2.volume'],
            'volume teks' => ['1.3.2.10.01.02.002,BOSP,Laptop,,,10,2,2026,Unit,abc,0', 'baris.2.volume'],
            'harga negatif' => ['1.3.2.10.01.02.002,BOSP,Laptop,,,10,2,2026,Unit,1,-100', 'baris.2.harga_satuan'],
            'tanggal mustahil' => ['1.3.2.10.01.02.002,BOSP,Laptop,,,31,2,2026,Unit,1,0', 'baris.2.tanggal_perolehan'],
        ];
    }

    /**
     * @dataProvider nilaiImporTidakValid
     */
    #[DataProvider('nilaiImporTidakValid')]
    public function test_impor_menolak_nilai_tidak_valid(string $baris, string $kunci): void
    {
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang,Merek / Type,Bahan,Tanggal,Bulan Perolehan,Tahun Perolehan,Satuan,Volume,Harga Satuan\n"
            .$baris."\n";

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), [
                'file' => UploadedFile::fake()->createWithContent('invalid.csv', $csv),
            ])
            ->assertSessionHasErrors($kunci);

        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_impor_berkas_kosong_ditolak(): void
    {
        $csv = "Kode Barang,Sumber Perolehan,Nama Barang\n";

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.import'), [
                'file' => UploadedFile::fake()->createWithContent('kosong.csv', $csv),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_template_impor_bisa_diunduh(): void
    {
        $response = $this->actingAs($this->operator)->get(route('pencatatan-bm.inventaris.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Header harus sama dengan urutan yang dibaca parser impor, kalau tidak
        // berkas yang diunduh operator akan masuk ke kolom yang salah.
        $base = $response->baseResponse;

        if (! $base instanceof BinaryFileResponse) {
            $this->fail('Template impor harus dikirim sebagai unduhan berkas.');
        }

        $spreadsheet = IOFactory::load($base->getFile()->getPathname());
        $header = $spreadsheet->getActiveSheet()->rangeToArray('A1:K1')[0];

        $this->assertSame([
            'Kode Barang', 'Sumber Perolehan', 'Nama Barang', 'Merek / Type', 'Bahan',
            'Tanggal', 'Bulan Perolehan', 'Tahun Perolehan', 'Satuan', 'Volume', 'Harga Satuan',
        ], $header);
    }

    public function test_sekolah_id_dari_request_diabaikan(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Lain Daftar',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
                'tanggal_perolehan' => '2026-02-10',
                'sekolah_id' => $lain->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->sekolah->id, InventarisUnit::firstOrFail()->sekolah_id);
    }

    public function test_tanpa_izin_kelola_inventaris_ditolak(): void
    {
        $pengguna = activity()->withoutLogging(fn () => User::create([
            'name' => 'Tanpa Izin',
            'username' => 'tanpa_izin_inv',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $pengguna->assignRole('operator_divisi');

        $this->actingAs($pengguna)
            ->post(route('pencatatan-bm.inventaris.store'), [
                'kode_barang' => '1.3.2.10.01.02.002',
                'volume' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_halaman_inventaris_menandai_hak_kelola(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canKelola', true));

        $pengguna = activity()->withoutLogging(fn () => User::create([
            'name' => 'Hanya Lihat',
            'username' => 'hanya_lihat_inv',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $pengguna->assignRole('operator_divisi');

        $this->actingAs($pengguna)
            ->get(route('pencatatan-bm.inventaris.index'))
            ->assertForbidden();
    }
}
