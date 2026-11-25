<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard lima kartu + rincian per kondisi, dan halaman Data Pengguna Barang
 * (aset per pegawai dengan tanggal BAST terakhir).
 */
class InventarisDashboardPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Pegawai $pegawai;

    private InventarisUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Dashboard',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Dashboard',
            'username' => 'op_dashboard',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        $this->pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
            'nip' => '198001012005011001',
        ]));

        $this->unit = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];
    }

    public function test_dashboard_menampilkan_lima_kartu_termasuk_barang_hilang(): void
    {
        $this->unit->update(['kondisi' => 'H']);

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ringkasan.totalUnit', 1)
                ->where('ringkasan.kondisi.H', 1)
                ->where('ringkasan.kondisi.B', 0)
                ->where('ringkasan.kondisi.RR', 0)
                ->where('ringkasan.kondisi.RB', 0));
    }

    public function test_statistik_mengembalikan_ringkasan_yang_sama_dengan_halaman(): void
    {
        $this->unit->update(['kondisi' => 'RR']);

        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.statistik'))
            ->assertOk()
            ->assertJsonPath('ringkasan.totalUnit', 1)
            ->assertJsonPath('ringkasan.kondisi.RR', 1)
            ->assertJsonPath('ringkasan.kondisi.B', 0);
    }

    public function test_statistik_operator_sekolah_lain_tidak_melihat_unit_ini(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Statistik Lain',
            'kota_kab' => 'Kota Bogor',
        ]));
        $operatorLain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain',
            'username' => 'op_stat_lain',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $lain->id,
            'password_changed_at' => now(),
        ]));
        $operatorLain->assignRole('operator_sekolah');

        $this->actingAs($operatorLain)
            ->getJson(route('pencatatan-bm.statistik'))
            ->assertOk()
            ->assertJsonPath('ringkasan.totalUnit', 0);
    }

    public function test_rincian_kondisi_mengembalikan_baris_json(): void
    {
        $ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
        ]));

        $this->unit->update(['kondisi' => 'RR', 'ruang_id' => $ruang->id, 'pegawai_id' => $this->pegawai->id]);

        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.kondisi.rincian', 'RR'))
            ->assertOk()
            ->assertJsonFragment([
                'nama_barang' => 'Laptop',
                'nomor_register' => '001',
                'ruang' => 'Ruang Guru',
                'pegawai' => 'Budi Santoso',
            ]);
    }

    public function test_rincian_unit_belum_disalurkan_mengembalikan_null(): void
    {
        $this->unit->update(['kondisi' => 'RB']);

        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.kondisi.rincian', 'RB'))
            ->assertOk()
            ->assertJsonPath('0.ruang', null)
            ->assertJsonPath('0.pegawai', null);
    }

    public function test_data_pengguna_mengelompokkan_aset_per_pegawai(): void
    {
        $this->unit->update(['pegawai_id' => $this->pegawai->id]);

        activity()->withoutLogging(fn () => Dokumen::create([
            'sekolah_id' => $this->sekolah->id,
            'jenis' => 'bast',
            'nomor' => '001/AR.03.05',
            'tanggal' => '2026-03-12',
            'inventaris_unit_id' => $this->unit->id,
            'payload' => ['units' => []],
        ]));

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengguna.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('kelompok', 1)
                ->where('kelompok.0.pegawai.nama', 'Budi Santoso')
                ->where('kelompok.0.jumlah_unit', 1)
                ->where('kelompok.0.items.0.tanggal_bast', '2026-03-12'));
    }
}
