<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga Review Focus #2: UUID unit sekolah lain tidak cukup untuk membaca
 * atau mengubahnya.
 */
class InventarisTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function user(?Sekolah $sekolah, string $role, string $username): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah?->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole($role);

        return $user;
    }

    private function unit(Sekolah $sekolah, string $kode = '5.2.02.01.004'): InventarisUnit
    {
        KunciLaporan::firstOrCreate(
            ['sekolah_id' => $sekolah->id, 'bulan' => 3, 'tahun' => 2026],
            ['status_kirim' => 'disetujui'],
        );

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => $kode,
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);

        return InventarisUnit::where('realisasi_id', $realisasi->id)->firstOrFail();
    }

    public function test_daftar_hanya_berisi_unit_sekolah_sendiri(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $this->unit($a);
        $unitB = $this->unit($b);

        $operatorA = $this->user($a, 'operator_sekolah', 'op_a');

        $this->actingAs($operatorA)
            ->get(route('pencatatan-bm.inventaris.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('units.data', 1)
                ->where('units.data.0.sekolah_id', $a->id));
    }

    public function test_operator_tidak_bisa_membaca_detail_unit_sekolah_lain(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $unitB = $this->unit($b);

        $this->actingAs($this->user($a, 'operator_sekolah', 'op_a'))
            ->get(route('pencatatan-bm.inventaris.show', $unitB))
            ->assertNotFound();
    }

    public function test_operator_tidak_bisa_mengubah_kondisi_unit_sekolah_lain(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $unitB = $this->unit($b);

        $this->actingAs($this->user($a, 'operator_sekolah', 'op_a'))
            ->put(route('pencatatan-bm.inventaris.kondisi', $unitB), ['kondisi' => 'RB', 'keterangan' => 'x'])
            ->assertNotFound();

        $this->assertSame('B', $unitB->fresh()->kondisi);
    }

    public function test_operator_tidak_bisa_mengubah_ruang_sekolah_lain(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $ruangB = activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $b->id, 'nama_ruang' => 'Ruang B']));

        $this->actingAs($this->user($a, 'operator_sekolah', 'op_a'))
            ->put(route('master-data.ruang.update', $ruangB), ['nama_ruang' => 'Diambil Alih'])
            ->assertNotFound();

        $this->assertSame('Ruang B', $ruangB->fresh()->nama_ruang);
    }

    public function test_operator_tidak_bisa_menghapus_pegawai_sekolah_lain(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $pegawaiB = activity()->withoutLogging(fn () => Pegawai::create(['sekolah_id' => $b->id, 'nama_lengkap' => 'Pegawai B']));

        $this->actingAs($this->user($a, 'operator_sekolah', 'op_a'))
            ->delete(route('master-data.pegawai.destroy', $pegawaiB))
            ->assertNotFound();

        $this->assertNotSoftDeleted('master_data_pegawai', ['id' => $pegawaiB->id]);
    }

    public function test_operator_sekolah_tanpa_sekolah_id_ditolak(): void
    {
        $user = $this->user(null, 'operator_sekolah', 'op_tanpa_sekolah');

        $this->actingAs($user)->get(route('pencatatan-bm.inventaris.index'))->assertForbidden();
    }

    public function test_sekolah_id_dari_query_diabaikan_untuk_operator(): void
    {
        $a = $this->sekolah('SMA A');
        $b = $this->sekolah('SMA B');
        $this->unit($b);

        $this->actingAs($this->user($a, 'operator_sekolah', 'op_a'))
            ->get(route('pencatatan-bm.inventaris.index', ['sekolah_id' => $b->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('units.data', 0));
    }
}
