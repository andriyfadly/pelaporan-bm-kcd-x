<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rekap berita acara: edit dan batalkan nomor BA tanpa menghapus dokumen.
 */
class InventarisBeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private InventarisUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA BA',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator BA',
            'username' => 'op_ba',
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

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-12', 'nomor' => 'BAST-01'])
            ->assertRedirect();
    }

    public function test_rekap_menampilkan_berita_acara_bast(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.berita-acara.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('daftar', 1)
                ->where('daftar.0.nomor', 'BAST-01'));
    }

    public function test_nomor_ba_bisa_dikoreksi(): void
    {
        $dokumen = Dokumen::firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.berita-acara.update', $dokumen), ['nomor' => 'BAST-02/2026'])
            ->assertRedirect();

        $this->assertSame('BAST-02/2026', $dokumen->fresh()->nomor);
    }

    public function test_batal_nomor_ba_menyimpan_alasan_tanpa_menghapus_dokumen(): void
    {
        $dokumen = Dokumen::firstOrFail();

        $this->actingAs($this->operator)
            ->delete(route('pencatatan-bm.berita-acara.destroy', $dokumen), ['alasan' => 'Salah input'])
            ->assertRedirect();

        $dokumen->refresh();
        $this->assertNull($dokumen->nomor);
        $this->assertNotNull($dokumen->nomor_dibatalkan_pada);
        $this->assertSame('Salah input', $dokumen->payload['pembatalan']['alasan']);
        $this->assertSame(1, Dokumen::count(), 'Dokumen tidak boleh hard-delete.');
    }

    public function test_tidak_bisa_mengubah_nomor_ba_sekolah_lain(): void
    {
        $dokumen = Dokumen::firstOrFail();

        $lain = activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain BA', 'kota_kab' => 'Kota Bandung']));
        $operatorLain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain BA',
            'username' => 'op_lain_ba',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $lain->id,
            'password_changed_at' => now(),
        ]));
        $operatorLain->assignRole('operator_sekolah');

        $this->actingAs($operatorLain)
            ->put(route('pencatatan-bm.berita-acara.update', $dokumen), ['nomor' => 'DICURI'])
            ->assertNotFound();

        $this->assertSame('BAST-01', $dokumen->fresh()->nomor);
    }

    public function test_relasi_model_histori_dan_foto(): void
    {
        $mutasi = Mutasi::create([
            'sekolah_id' => $this->sekolah->id,
            'inventaris_unit_id' => $this->unit->id,
            'jenis' => 'penempatan',
            'tujuan_kondisi' => 'B',
            'tanggal' => '2026-03-10',
            'user_id' => $this->operator->id,
        ]);

        $foto = Foto::create([
            'sekolah_id' => $this->sekolah->id,
            'inventaris_unit_id' => $this->unit->id,
            'path' => 'inventaris/foto/x.jpg',
            'mime' => 'image/jpeg',
            'size' => 10,
            'checksum' => str_repeat('b', 64),
            'uploaded_by' => $this->operator->id,
        ]);

        $dokumen = Dokumen::firstOrFail();

        $this->assertTrue($mutasi->sekolah->is($this->sekolah));        $this->assertTrue($mutasi->unit->is($this->unit));
        $this->assertTrue($mutasi->user->is($this->operator));
        $this->assertTrue($foto->sekolah->is($this->sekolah));
        $this->assertTrue($foto->unit->is($this->unit));
        $this->assertTrue($foto->uploader->is($this->operator));
        $this->assertSame(1, $this->unit->foto()->count());
        $this->assertTrue($dokumen->user->is($this->operator));
    }
}
