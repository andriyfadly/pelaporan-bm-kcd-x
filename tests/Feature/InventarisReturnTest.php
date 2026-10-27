<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga Review Focus #5 (bagian histori): ruang/pegawai yang masih
 * direferensikan tidak boleh menghilangkan jejak mutasi.
 */
class InventarisReturnTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private InventarisUnit $unit;

    private Ruang $ruang;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Kembali',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Kembali',
            'username' => 'op_kembali',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        KunciLaporan::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ]);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);
        $this->unit = InventarisUnit::firstOrFail();

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));
        $this->pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi',
        ]));
    }

    private function tempatkan(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruang->id,
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();
    }

    public function test_pengembalian_menutup_penempatan_dan_mencatat_kondisi(): void
    {
        $this->tempatkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), [
                'kondisi' => 'RR',
                'tanggal' => '2026-05-01',
                'keterangan' => 'Dikembalikan ke gudang',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertSame(InventarisUnit::STATUS_DIKEMBALIKAN, $unit->status);
        $this->assertSame('RR', $unit->kondisi);
        $this->assertNull($unit->ruang_id);
        $this->assertNull($unit->pegawai_id);
        $this->assertSame($this->sekolah->id, $unit->sekolah_id);

        $mutasi = Mutasi::where('inventaris_unit_id', $unit->id)->where('jenis', 'pengembalian')->firstOrFail();
        $this->assertSame($this->ruang->id, $mutasi->asal_ruang_id);
        $this->assertNull($mutasi->tujuan_ruang_id);
        $this->assertSame('RR', $mutasi->tujuan_kondisi);
        $this->assertSame('B', $mutasi->asal_kondisi);
    }

    public function test_pengembalian_tanpa_kondisi_ditolak(): void
    {
        $this->tempatkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), ['tanggal' => '2026-05-01'])
            ->assertSessionHasErrors('kondisi');
    }

    public function test_pengembalian_unit_yang_belum_ditempatkan_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), ['kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Mutasi::count());
    }

    public function test_pengembalian_dua_kali_ditolak(): void
    {
        $this->tempatkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), ['kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), ['kondisi' => 'B', 'tanggal' => '2026-05-02'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(1, Mutasi::where('inventaris_unit_id', $this->unit->id)->where('jenis', 'pengembalian')->count());
    }

    public function test_tidak_bisa_mengembalikan_unit_sekolah_lain(): void
    {
        $this->tempatkan();

        $lain = activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain', 'kota_kab' => 'Kota Bandung']));
        $operatorLain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain',
            'username' => 'op_lain',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $lain->id,
            'password_changed_at' => now(),
        ]));
        $operatorLain->assignRole('operator_sekolah');

        $this->actingAs($operatorLain)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), ['kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertNotFound();

        $this->assertSame(InventarisUnit::STATUS_AKTIF, $this->unit->fresh()->status);
    }

    public function test_menghapus_ruang_tidak_menghapus_histori_mutasi(): void
    {
        $this->tempatkan();

        $this->actingAs($this->operator)
            ->delete(route('master-data.ruang.destroy', $this->ruang))
            ->assertRedirect();

        $mutasi = Mutasi::where('inventaris_unit_id', $this->unit->id)->firstOrFail();

        $this->assertSoftDeleted('master_data_ruang', ['id' => $this->ruang->id]);
        $this->assertSame(1, Mutasi::count(), 'Histori mutasi tidak boleh ikut terhapus.');
        // FK nullOnDelete hanya melepas referensi; baris histori tetap ada.
        $this->assertNotNull($mutasi->fresh());
        $this->assertNotNull($mutasi->fresh()->tanggal);
    }

    public function test_menghapus_pegawai_tidak_menghapus_histori_mutasi(): void
    {
        $this->tempatkan();

        $this->actingAs($this->operator)
            ->delete(route('master-data.pegawai.destroy', $this->pegawai))
            ->assertRedirect();

        $this->assertSoftDeleted('master_data_pegawai', ['id' => $this->pegawai->id]);
        $this->assertSame(1, Mutasi::count());

        // Pegawai terhapus tidak lagi muncul sebagai penanggung jawab, tetapi
        // unit & histori tetap menyimpan ID-nya.
        $unit = InventarisUnit::firstOrFail();
        $this->assertNull($unit->pegawai);
        $this->assertSame($this->pegawai->id, $unit->getOriginal('pegawai_id'));
    }

    public function test_histori_mutasi_tidak_bisa_di_hard_delete_lewat_relasi(): void
    {
        $this->tempatkan();
        $mutasi = Mutasi::firstOrFail();

        $this->expectException(QueryException::class);

        // Unit direferensikan mutasi dengan FK restrict.
        $this->unit->forceDelete();
        $this->assertNotNull($mutasi->fresh());
    }
}
