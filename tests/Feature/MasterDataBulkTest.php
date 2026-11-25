<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Tambah massal, impor, dan hapus semua master pegawai/ruang — paritas
 * `data_pegawai.php` / `data_ruang.php` acuan.
 */
class MasterDataBulkTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Bulk',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Bulk',
            'username' => 'op_bulk',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');
    }

    public function test_tambah_banyak_pegawai_menyimpan_semua_baris(): void
    {
        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.massal'), [
                'baris' => [
                    ['nama_lengkap' => 'Budi Santoso', 'nip' => '198001012005011001', 'pangkat_golongan' => 'III/a'],
                    ['nama_lengkap' => 'Siti Aminah', 'nip' => null, 'pangkat_golongan' => null],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data pegawai berhasil disimpan');

        $this->assertSame(2, Pegawai::where('sekolah_id', $this->sekolah->id)->count());
        $this->assertDatabaseHas('master_data_pegawai', ['nama_lengkap' => 'Siti Aminah', 'nip' => null]);
    }

    public function test_tambah_banyak_pegawai_menolak_duplikat_di_dalam_request(): void
    {
        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.massal'), [
                'baris' => [
                    ['nama_lengkap' => 'Budi Santoso'],
                    ['nama_lengkap' => 'budi santoso'],
                ],
            ])
            ->assertSessionHasErrors('baris');

        $this->assertSame(0, Pegawai::count());
    }

    public function test_tambah_banyak_pegawai_menolak_nama_atau_nip_yang_sudah_ada(): void
    {
        Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso']);

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.massal'), [
                'baris' => [['nama_lengkap' => 'Orang Lain', 'nip' => null]],
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.massal'), [
                'baris' => [['nama_lengkap' => 'Budi Santoso']],
            ])
            ->assertSessionHasErrors('baris');

        $this->assertSame(2, Pegawai::count());
    }

    public function test_impor_pegawai_melewati_duplikat_dan_menghitung_yang_masuk(): void
    {
        Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso']);

        $berkas = UploadedFile::fake()->createWithContent(
            'pegawai.csv',
            "Nama Lengkap,NIP,Pangkat/Golongan\nBudi Santoso,198001012005011001,III/a\nSiti Aminah,199002022010012002,III/b\nAndi Wijaya,,\n",
        );

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.import'), ['file' => $berkas])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data pegawai berhasil diimport (1 dilewati karena sudah ada)');

        $this->assertSame(3, Pegawai::count());
    }

    public function test_impor_pegawai_semua_duplikat_memberi_pesan_gagal(): void
    {
        Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso']);

        $berkas = UploadedFile::fake()->createWithContent(
            'pegawai.csv',
            "Nama Lengkap,NIP,Pangkat/Golongan\nBudi Santoso,,\n",
        );

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.import'), ['file' => $berkas])
            ->assertSessionHas('error', 'Gagal Import: Semua data pegawai dalam file Excel sudah pernah diinputkan!');

        $this->assertSame(1, Pegawai::count());
    }

    public function test_template_pegawai_dan_ruang_bisa_diunduh(): void
    {
        $this->actingAs($this->operator)
            ->get(route('master-data.pegawai.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-pegawai.xlsx');

        $this->actingAs($this->operator)
            ->get(route('master-data.ruang.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-ruang.xlsx');
    }

    public function test_hapus_semua_pegawai_melepas_penanggung_jawab_unit(): void
    {
        $pegawai = Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso']);
        $unit = $this->unit(['pegawai_id' => $pegawai->id]);

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.destroy-semua'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Seluruh data pegawai berhasil dihapus');

        $this->assertSoftDeleted('master_data_pegawai', ['id' => $pegawai->id]);
        // Baris tetap menyimpan FK untuk histori; relasi terbaca null karena
        // soft delete, sama seperti hapus satu pegawai.
        $this->assertNull($unit->fresh()->pegawai);
        $this->assertSame($pegawai->id, $unit->fresh()->getOriginal('pegawai_id'));
    }

    public function test_tambah_banyak_ruang_dan_hapus_semua(): void
    {
        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.massal'), [
                'baris' => [
                    ['nama_ruang' => 'Ruang Guru', 'kode' => 'RG'],
                    ['nama_ruang' => 'Ruang Tata Usaha', 'kode' => null],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data ruang berhasil ditambahkan');

        $this->assertSame(2, Ruang::count());

        $unit = $this->unit(['ruang_id' => Ruang::first()->id]);

        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.destroy-semua'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Seluruh data ruang berhasil dihapus');

        $this->assertSame(0, Ruang::count());
        $this->assertNull($unit->fresh()->ruang);
        $this->assertNotNull($unit->fresh()->getOriginal('ruang_id'));
    }

    public function test_tambah_banyak_ruang_menolak_nama_duplikat(): void
    {
        Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']);

        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.massal'), [
                'baris' => [['nama_ruang' => 'ruang guru']],
            ])
            ->assertSessionHasErrors('baris');

        $this->assertSame(1, Ruang::count());
    }

    public function test_impor_ruang_melewati_duplikat(): void
    {
        Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']);

        $berkas = UploadedFile::fake()->createWithContent(
            'ruang.csv',
            "Nama Ruangan,Kode\nRuang Guru,RG\nLab Komputer,\n",
        );

        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.import'), ['file' => $berkas])
            ->assertRedirect()
            ->assertSessionHas('success', '1 data ruang berhasil diimport (1 dilewati karena sudah ada)');

        $this->assertSame(2, Ruang::count());
    }

    public function test_hapus_semua_hanya_menyentuh_sekolah_sendiri(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Bulk Lain',
            'kota_kab' => 'Kota Bogor',
        ]));
        Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi']);
        Pegawai::create(['sekolah_id' => $lain->id, 'nama_lengkap' => 'Joko']);

        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.destroy-semua'))
            ->assertRedirect();

        $this->assertSame(1, Pegawai::where('sekolah_id', $lain->id)->count());
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function unit(array $atribut = []): InventarisUnit
    {
        $unit = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        if ($atribut !== []) {
            $unit->update($atribut);
        }

        return $unit->fresh();
    }
}
