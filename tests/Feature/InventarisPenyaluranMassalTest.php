<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buku Penyaluran: simpan massal satu baris = satu rentang register, restart
 * penyaluran, dan edit penerima.
 */
class InventarisPenyaluranMassalTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Ruang $ruang;

    /** @var array<int, InventarisUnit> */
    private array $units = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Massal',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Massal',
            'username' => 'op_massal',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
        ]));

        $this->units = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 3,
            'tanggal_perolehan' => '2026-03-10',
        ]);

        activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
        ]));
    }

    public function test_simpan_massal_menyalurkan_semua_unit_dalam_baris(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => array_map(fn (InventarisUnit $unit): string => $unit->id, $this->units),
                    'ruang_id' => $this->ruang->id,
                    'tanggal' => '2026-03-11',
                    'keterangan' => 'Penyaluran awal',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, Mutasi::where('jenis', 'penyaluran')->count());
        $this->assertSame(3, InventarisUnit::where('ruang_id', $this->ruang->id)->count());
    }

    public function test_satu_baris_tampil_sebagai_rentang_register(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.penyaluran.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('baris', 1)
                ->where('baris.0.nomor_register', '001-003')
                ->where('baris.0.volume', 3)
                ->where('baris.0.sudah_disalurkan', false));
    }

    public function test_baris_mengirim_volume_pengadaan_dan_nomor_bast(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.penyaluran.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Acuan: Vol pengadaan = total unit satu batch pembelian, berbeda
                // dari Vol penyaluran yang mengikuti rentang register.
                ->where('baris.0.volume_pengadaan', 3)
                ->where('baris.0.volume', 3)
                ->where('nomorBastBerikutnya', '001'));
    }

    public function test_volume_pengadaan_tetap_saat_baris_dipecah_menjadi_dua_run(): void
    {
        InventarisUnit::query()->where('nomor_register', '003')->update(['nomor_register' => '010']);

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.penyaluran.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('baris', 2)
                ->where('baris.0.volume', 2)
                ->where('baris.0.volume_pengadaan', 3)
                ->where('baris.1.volume', 1)
                ->where('baris.1.volume_pengadaan', 3));
    }

    public function test_vol_menyalurkan_sebagian_unit_dalam_baris(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => array_map(fn (InventarisUnit $unit): string => $unit->id, $this->units),
                    'vol' => 2,
                    'ruang_id' => $this->ruang->id,
                    'tanggal' => '2026-03-11',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, InventarisUnit::where('ruang_id', $this->ruang->id)->count());
        $this->assertSame(1, InventarisUnit::whereNull('ruang_id')->count());
        $this->assertSame(2, Mutasi::where('jenis', 'penyaluran')->count());

        // Sisa baris dilaporkan supaya UI bisa menawarkan penyaluran lanjutan.
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.penyaluran.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('baris.0.sisa', 1)
                ->where('baris.0.sudah_disalurkan', false));
    }

    public function test_vol_melebihi_sisa_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.massal'), [
                'baris' => [[
                    'unit_ids' => array_map(fn (InventarisUnit $unit): string => $unit->id, $this->units),
                    'vol' => 4,
                    'ruang_id' => $this->ruang->id,
                    'tanggal' => '2026-03-11',
                ]],
            ])
            ->assertSessionHasErrors('baris');

        $this->assertSame(0, Mutasi::count());
    }

    public function test_restart_penyaluran_mengosongkan_lokasi_dan_mencatat_koreksi(): void
    {
        $unit = $this->units[0];
        $unit->update(['ruang_id' => $this->ruang->id]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.reset', $unit))
            ->assertRedirect()
            ->assertSessionHas('success');

        $unit = $unit->fresh();

        $this->assertNull($unit->ruang_id);
        $this->assertNull($unit->pegawai_id);
        $this->assertSame(InventarisUnit::STATUS_AKTIF, $unit->status);
        $this->assertSame(
            1,
            Mutasi::where('inventaris_unit_id', $unit->id)
                ->where('keterangan', 'Penyaluran direset')
                ->count(),
        );
    }

    public function test_restart_ditolak_untuk_unit_yang_sudah_dikembalikan(): void
    {
        $unit = $this->units[0];
        $unit->update(['status' => InventarisUnit::STATUS_DIKEMBALIKAN]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.reset', $unit))
            ->assertSessionHasErrors('unit');

        $this->assertSame(InventarisUnit::STATUS_DIKEMBALIKAN, $unit->fresh()->status);
    }

    public function test_ubah_penerima_memindahkan_lokasi(): void
    {
        $unit = $this->units[0];
        $unit->update(['ruang_id' => $this->ruang->id]);

        $tujuan = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Tata Usaha',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.penerima', $unit), ['ruang_id' => $tujuan->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($tujuan->id, $unit->fresh()->ruang_id);
    }
}
