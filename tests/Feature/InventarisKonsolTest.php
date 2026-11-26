<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisRegisterSequence;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsol inventaris: edit, hapus, hapus semua, dan pratinjau nomor register.
 */
class InventarisKonsolTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private Sekolah $sekolahLain;

    private User $operator;

    private InventarisUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Konsol',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->sekolahLain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Konsol Lain',
            'kota_kab' => 'Kota Bogor',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Konsol',
            'username' => 'op_konsol',
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
            'harga_satuan' => 9000000,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];
    }

    private function payload(array $ganti = []): array
    {
        return array_merge([
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'bahan' => 'Logam',
            'satuan' => 'Unit',
            'harga_satuan' => 9500000,
            'nilai_perolehan_unit' => 9500000,
            'asal_perolehan' => 'BOSP',
            'tanggal_perolehan' => '2026-03-10',
            'kondisi' => 'B',
        ], $ganti);
    }

    public function test_edit_mengubah_atribut_tanpa_menggeser_register(): void
    {
        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.update', $this->unit), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $unit = $this->unit->fresh();

        $this->assertSame('Lenovo', $unit->merk_tipe);
        $this->assertSame('Logam', $unit->bahan);
        $this->assertSame('001', $unit->nomor_register);
        $this->assertSame('9500000.00', $unit->harga_satuan);
    }

    public function test_edit_menolak_register_bentrok_saat_kode_diubah(): void
    {
        app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.003',
            'nama_barang' => 'Proyektor',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ]);

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.update', $this->unit), $this->payload(['kode_barang' => '1.3.2.10.01.02.003']))
            ->assertSessionHasErrors('kode_barang');

        $this->assertSame('1.3.2.10.01.02.002', $this->unit->fresh()->kode_barang);
    }

    public function test_hapus_menandai_unit_dan_mengeluarkannya_dari_daftar(): void
    {
        $this->actingAs($this->operator)
            ->delete(route('pencatatan-bm.inventaris.destroy', $this->unit))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(InventarisUnit::STATUS_DIHAPUS_MANUAL, $this->unit->fresh()->status);

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('units.data', 0));
    }

    public function test_hapus_semua_hanya_menyentuh_sekolah_sendiri(): void
    {
        $lain = app(PendaftaranInventarisService::class)->daftarkan($this->sekolahLain->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop Lain',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.destroy-semua'), ['konfirmasi' => 'HAPUS SEMUA'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(InventarisUnit::STATUS_DIHAPUS_MANUAL, $this->unit->fresh()->status);
        $this->assertSame(InventarisUnit::STATUS_AKTIF, $lain->fresh()->status);
    }

    public function test_hapus_semua_menolak_konfirmasi_salah(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.destroy-semua'), ['konfirmasi' => 'hapus'])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertSame(InventarisUnit::STATUS_AKTIF, $this->unit->fresh()->status);
    }

    public function test_kondisi_massal_mengubah_semua_unit_dan_mencatat_koreksi(): void
    {
        $kedua = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi-massal'), [
                'unit_ids' => [$this->unit->id, $kedua->id],
                'kondisi' => [$this->unit->id => 'RR', $kedua->id => 'RB'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Kondisi detail seluruh unit berhasil disimpan!');

        $this->assertSame('RR', $this->unit->fresh()->kondisi);
        $this->assertSame('RB', $kedua->fresh()->kondisi);
        $this->assertSame(2, Mutasi::where('jenis', 'koreksi')->count());
    }

    public function test_kondisi_massal_menolak_unit_sekolah_lain(): void
    {
        $lain = app(PendaftaranInventarisService::class)->daftarkan($this->sekolahLain->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi-massal'), [
                'unit_ids' => [$this->unit->id, $lain->id],
                'kondisi' => [$this->unit->id => 'RR', $lain->id => 'RR'],
            ])
            ->assertNotFound();

        $this->assertSame(InventarisUnit::KONDISI_BELUM_DISET, $this->unit->fresh()->kondisi);
    }

    public function test_pratinjau_register_tidak_menaikkan_sequence(): void
    {
        $response = $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.inventaris.register-berikutnya', [
                'kode_barang' => '1.3.2.10.01.02.002',
                'tahun' => 2026,
            ]))
            ->assertOk();

        $this->assertSame('002', $response->json('register'));
        $this->assertSame(
            2,
            (int) InventarisRegisterSequence::query()
                ->where('sekolah_id', $this->sekolah->id)
                ->where('kode_barang', '1.3.2.10.01.02.002')
                ->where('tahun', 2026)
                ->value('next_number'),
        );
    }

    public function test_pratinjau_register_kode_baru_mulai_dari_001(): void
    {
        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.inventaris.register-berikutnya', [
                'kode_barang' => '1.3.2.10.01.02.999',
                'tahun' => 2026,
            ]))
            ->assertOk()
            ->assertJson(['register' => '001']);

        $this->assertSame(0, InventarisRegisterSequence::query()
            ->where('kode_barang', '1.3.2.10.01.02.999')
            ->count());
    }
}
