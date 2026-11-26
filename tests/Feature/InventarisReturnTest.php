<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    private function salurkan(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => [$this->unit->id],
                    'ruang_id' => $this->ruang->id,
                    'pegawai_id' => $this->pegawai->id,
                    'tanggal' => '2026-03-10',
                ]],
            ])
            ->assertRedirect();

        // Pengembalian menunggu nomor BA diisi, sama seperti aplikasi acuan.
        $this->buatBast();
    }

    private function buatBast(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), [
                'tanggal' => '2026-03-12',
                'nomor' => 'BAST-01',
            ])
            ->assertRedirect();
    }

    public function test_daftar_pengembalian_hanya_unit_berbast(): void
    {
        $this->salurkan();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('baris', 1)
                ->where('baris.0.nomor_bast_terakhir', 'BAST-01'));
    }

    public function test_pengembalian_tanpa_bast_tidak_muncul_dan_ditolak_saat_dipaksa(): void
    {
        // Ditempatkan tanpa BAST: daftar tidak menampilkannya dan request
        // langsung ditolak. BAST wajib terbit lebih dulu (paritas acuan).
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => [$this->unit->id],
                    'ruang_id' => $this->ruang->id,
                    'tanggal' => '2026-03-10',
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('baris', 0));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(InventarisUnit::STATUS_AKTIF, $this->unit->fresh()->status);
        $this->assertSame(0, Dokumen::where('jenis', 'pengembalian')->count());
    }

    public function test_pengembalian_menutup_penempatan_dan_mencatat_kondisi(): void
    {
        $this->salurkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id],
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
        $this->salurkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'tanggal' => '2026-05-01'])
            ->assertSessionHasErrors('kondisi');
    }

    public function test_pengembalian_unit_yang_belum_ditempatkan_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Mutasi::count());
    }

    public function test_pengembalian_dua_kali_ditolak(): void
    {
        $this->salurkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'kondisi' => 'B', 'tanggal' => '2026-05-02'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(1, Mutasi::where('inventaris_unit_id', $this->unit->id)->where('jenis', 'pengembalian')->count());
    }

    public function test_tidak_bisa_mengembalikan_unit_sekolah_lain(): void
    {
        $this->salurkan();

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
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id], 'kondisi' => 'B', 'tanggal' => '2026-05-01'])
            ->assertNotFound();

        $this->assertSame(InventarisUnit::STATUS_AKTIF, $this->unit->fresh()->status);
    }

    public function test_menghapus_ruang_tidak_menghapus_histori_mutasi(): void
    {
        $this->salurkan();

        $this->actingAs($this->operator)
            ->delete(route('master-data.ruang.destroy', $this->ruang))
            ->assertRedirect();

        $mutasi = Mutasi::where('inventaris_unit_id', $this->unit->id)->firstOrFail();

        $this->assertSoftDeleted('master_data_ruang', ['id' => $this->ruang->id]);
        $this->assertSame(1, Mutasi::count(), 'Histori mutasi tidak boleh ikut terhapus.');
        // FK nullOnDelete hanya melepas referensi; baris histori tetap ada.
        $this->assertNotNull($mutasi->fresh());
    }

    public function test_menghapus_pegawai_tidak_menghapus_histori_mutasi(): void
    {
        $this->salurkan();

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

    public function test_pengembalian_banyak_unit_menerbitkan_satu_ba_untuk_satu_baris(): void
    {
        $units = $this->buatUnitBaris('5.2.02.01.004', 3);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => $units->pluck('id')->all(),
                    'ruang_id' => $this->ruang->id,
                    'pegawai_id' => $this->pegawai->id,
                    'tanggal' => '2026-03-10',
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => $units->pluck('id')->all(),
                'tanggal' => '2026-03-12',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), [
                'unit_ids' => $units->pluck('id')->all(),
                'kondisi' => 'RR',
                'tanggal' => '2026-05-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $pesan): bool => str_contains($pesan, 'Berita Acara Pengembalian'));

        $this->assertSame(3, Mutasi::where('jenis', 'pengembalian')->count());
        $this->assertSame(1, Dokumen::where('jenis', 'pengembalian')->count());

        $dokumen = Dokumen::where('jenis', 'pengembalian')->firstOrFail();
        $this->assertNull($dokumen->inventaris_unit_id);
        $this->assertCount(3, $dokumen->payload['units']);
        $this->assertCount(3, $dokumen->payload['mutasi_ids']);
        $this->assertEqualsCanonicalizing(
            $units->pluck('id')->all(),
            collect($dokumen->payload['units'])->pluck('id')->all(),
        );

        // Riwayat menampilkan satu baris per unit dengan nomor BA yang sama.
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('riwayat', 3)
                ->has('riwayatRingkas', 3)
                ->where('riwayat.0.nomor', $dokumen->nomor)
                ->where('riwayat.1.nomor', $dokumen->nomor)
                ->where('riwayat.2.nomor', $dokumen->nomor));
    }

    public function test_riwayat_ringkas_lima_dan_penuh_tanpa_batas(): void
    {
        $units = $this->buatUnitBaris('5.2.02.01.004', 6);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => $units->pluck('id')->all(),
                    'ruang_id' => $this->ruang->id,
                    'pegawai_id' => $this->pegawai->id,
                    'tanggal' => '2026-03-10',
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), ['unit_ids' => $units->pluck('id')->all()])
            ->assertRedirect();

        // Enam unit + unit utama = 7 baris histori pengembalian.
        $this->salurkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), [
                'unit_ids' => $units->pluck('id')->all(),
                'kondisi' => 'B',
                'tanggal' => '2026-05-01',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), [
                'unit_ids' => [$this->unit->id],
                'kondisi' => 'B',
                'tanggal' => '2026-05-02',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('riwayat', 7)
                ->has('riwayatRingkas', 5)
                ->where('riwayat.0.tanggal', '2026-05-02'));
    }

    public function test_pengembalian_banyak_unit_menolak_barang_berbeda(): void
    {
        $lain = $this->buatUnitBaris('5.2.02.01.005', 1)->firstOrFail();
        $this->salurkan();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => [$lain->id],
                    'ruang_id' => $this->ruang->id,
                    'pegawai_id' => $this->pegawai->id,
                    'tanggal' => '2026-03-10',
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), [
                'unit_ids' => [$this->unit->id, $lain->id],
                'kondisi' => 'B',
                'tanggal' => '2026-05-01',
            ])
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Mutasi::where('jenis', 'pengembalian')->count());
    }

    /**
     * Unit tambahan dengan barang yang sama, nomor register berurutan.
     *
     * @return Collection<int, InventarisUnit>
     */
    private function buatUnitBaris(string $kode, int $volume): Collection
    {
        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => $kode,
            'nama_barang' => 'Laptop',
            'volume' => $volume,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000 * $volume,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);

        return InventarisUnit::where('realisasi_id', $realisasi->id)
            ->orderBy('unit_index')
            ->get();
    }

    public function test_histori_mutasi_tidak_bisa_di_hard_delete_lewat_relasi(): void
    {
        $this->salurkan();
        $mutasi = Mutasi::firstOrFail();

        $this->expectException(QueryException::class);

        // Unit direferensikan mutasi dengan FK restrict.
        $this->unit->forceDelete();
        $this->assertNotNull($mutasi->fresh());
    }
}
