<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga paritas Pencatatan BM: sentinel kondisi, guard unit retired, dan
 * kelayakan BAST yang mengikuti penitipan saat ini.
 */
class InventarisParityGuardTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private InventarisUnit $unit;

    private Ruang $ruang;

    private Pegawai $pegawai;

    private Pegawai $pegawaiLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Paritas',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Paritas',
            'username' => 'op_paritas',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        $this->unit = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));
        $this->pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi',
        ]));
        $this->pegawaiLain = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Siti',
        ]));
    }

    private function tempatkan(?string $pegawaiId = null): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => [$this->unit->id],
                    'ruang_id' => $this->ruang->id,
                    'pegawai_id' => $pegawaiId ?? $this->pegawai->id,
                    'tanggal' => '2026-03-11',
                ]],
            ])
            ->assertRedirect();
    }

    private function terbitkanBast(string $nomor = 'BAST-01'): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), [
                'tanggal' => '2026-03-12',
                'nomor' => $nomor,
            ])
            ->assertRedirect();
    }

    public function test_detail_kondisi_b_memuat_unit_sentinel(): void
    {
        // Hasil impor menyimpan '-' dan dashboard menghitungnya sebagai Baik.
        $this->assertSame(InventarisUnit::KONDISI_BELUM_DISET, $this->unit->kondisi);

        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.kondisi.rincian', 'B'))
            ->assertOk()
            ->assertJsonPath('0.nomor_register', $this->unit->nomor_register);
    }

    public function test_unit_retired_ditolak_untuk_edit_hapus_dan_kondisi(): void
    {
        $this->unit->update(['status' => InventarisUnit::STATUS_DIHAPUS_MANUAL]);

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.update', $this->unit), $this->payloadUpdate())
            ->assertSessionHasErrors('unit');

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi', $this->unit), ['kondisi' => 'RR'])
            ->assertSessionHasErrors('unit');

        $this->actingAs($this->operator)
            ->delete(route('pencatatan-bm.inventaris.destroy', $this->unit))
            ->assertSessionHasErrors('unit');

        $this->assertSame(InventarisUnit::STATUS_DIHAPUS_MANUAL, $this->unit->fresh()->status);
    }

    public function test_edit_kondisi_mencatat_tepat_satu_koreksi(): void
    {
        $this->unit->update(['kondisi' => 'B']);

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.update', $this->unit), $this->payloadUpdate(['kondisi' => 'RR']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('RR', $this->unit->fresh()->kondisi);
        $this->assertSame(1, Mutasi::where('inventaris_unit_id', $this->unit->id)->count());
    }

    public function test_edit_nama_unit_sentinel_tidak_mengubah_kondisi(): void
    {
        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.update', $this->unit), $this->payloadUpdate([
                'nama_barang' => 'Laptop Baru',
                'kondisi' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $unit = $this->unit->fresh();
        $this->assertSame('Laptop Baru', $unit->nama_barang);
        $this->assertSame(InventarisUnit::KONDISI_BELUM_DISET, $unit->kondisi);
        $this->assertSame(0, Mutasi::where('inventaris_unit_id', $unit->id)->count());
    }

    public function test_route_berita_acara_tidak_menyentuh_dokumen_non_bast(): void
    {
        $dokumen = activity()->withoutLogging(fn () => Dokumen::create([
            'sekolah_id' => $this->sekolah->id,
            'jenis' => 'pengembalian',
            'nomor' => '001/PENG',
            'tanggal' => '2026-03-12',
            'payload' => [],
            'user_id' => $this->operator->id,
        ]));

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.berita-acara.update', $dokumen), ['nomor' => '002/BAST'])
            ->assertNotFound();

        $this->actingAs($this->operator)
            ->delete(route('pencatatan-bm.berita-acara.destroy', $dokumen), ['alasan' => 'salah'])
            ->assertNotFound();

        $this->assertSame('001/PENG', $dokumen->fresh()->nomor);
    }

    public function test_bast_hilang_kelayakan_setelah_penerima_berubah_dan_pulih_setelah_bast_baru(): void
    {
        $this->tempatkan();
        $this->terbitkanBast();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertInertia(fn ($page) => $page->has('baris', 1));

        // Pindah penerima: BAST lama tidak lagi mewakili penitipan.
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.penerima-massal'), [
                'unit_ids' => [$this->unit->id],
                'ruang_id' => $this->ruang->id,
                'pegawai_id' => $this->pegawaiLain->id,
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertInertia(fn ($page) => $page->has('baris', 0));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store'), [
                'unit_ids' => [$this->unit->id],
                'kondisi' => 'B',
                'tanggal' => '2026-05-01',
            ])
            ->assertSessionHasErrors('unit');

        // BAST baru untuk penerima saat ini mengembalikan kelayakan.
        $this->terbitkanBast('BAST-02');

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertInertia(fn ($page) => $page->has('baris', 1));
    }

    public function test_reset_setelah_bast_menghilangkan_kelayakan_pengembalian(): void
    {
        $this->tempatkan();
        $this->terbitkanBast();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.reset-massal'), ['unit_ids' => [$this->unit->id]])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertInertia(fn ($page) => $page->has('baris', 0));
    }

    public function test_nomor_bast_aktif_tidak_boleh_dipakai_ulang(): void
    {
        $this->tempatkan();
        $this->terbitkanBast('BAST-01');
        $this->terbitkanBast('BAST-02');

        $dokumen = Dokumen::where('jenis', 'bast')->where('nomor', 'BAST-01')->firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.berita-acara.update', $dokumen), ['nomor' => 'BAST-02'])
            ->assertSessionHasErrors('nomor');

        $this->assertSame('BAST-01', $dokumen->fresh()->nomor);
    }

    public function test_serial_bast_yang_dibatalkan_tidak_boleh_dipakai_ulang(): void
    {
        $this->tempatkan();
        $this->terbitkanBast('BAST-01');
        $this->terbitkanBast('BAST-02');

        $pertama = Dokumen::where('jenis', 'bast')->where('nomor', 'BAST-01')->firstOrFail();
        $kedua = Dokumen::where('jenis', 'bast')->where('nomor', 'BAST-02')->firstOrFail();

        $this->actingAs($this->operator)
            ->delete(route('pencatatan-bm.berita-acara.destroy', $kedua), ['alasan' => 'Salah input'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.berita-acara.update', $pertama), ['nomor' => 'BAST-02'])
            ->assertSessionHasErrors('nomor');
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function payloadUpdate(array $ganti = []): array
    {
        return array_merge([
            'kode_barang' => $this->unit->kode_barang,
            'nama_barang' => $this->unit->nama_barang,
            'merk_tipe' => $this->unit->merk_tipe,
            'bahan' => $this->unit->bahan,
            'satuan' => $this->unit->satuan,
            'tanggal_perolehan' => '2026-03-10',
            'harga_satuan' => 1000000,
            'asal_perolehan' => 'BOSP',
            'no_sertifikat' => null,
            'kondisi' => 'B',
            'keterangan' => null,
        ], $ganti);
    }
}
