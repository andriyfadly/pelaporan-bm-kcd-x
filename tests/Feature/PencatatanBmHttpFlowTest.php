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

class PencatatanBmHttpFlowTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Flow',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Flow',
            'username' => 'op_flow',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');
    }

    public function test_crud_ruang_lewat_http(): void
    {
        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.store'), ['nama_ruang' => 'Lab Komputer'])
            ->assertRedirect();

        $ruang = Ruang::where('sekolah_id', $this->sekolah->id)->firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('master-data.ruang.update', $ruang), ['nama_ruang' => 'Lab TIK', 'kode' => 'TIK'])
            ->assertRedirect();

        $this->assertSame('Lab TIK', $ruang->fresh()->nama_ruang);

        $this->actingAs($this->operator)
            ->delete(route('master-data.ruang.destroy', $ruang))
            ->assertRedirect();

        $this->assertSoftDeleted('master_data_ruang', ['id' => $ruang->id]);
    }

    public function test_nama_ruang_ganda_di_sekolah_sama_ditolak(): void
    {
        activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']));

        $this->actingAs($this->operator)
            ->post(route('master-data.ruang.store'), ['nama_ruang' => 'ruang guru'])
            ->assertSessionHasErrors('nama_ruang');
    }

    public function test_crud_pegawai_lewat_http(): void
    {
        $this->actingAs($this->operator)
            ->post(route('master-data.pegawai.store'), ['nama_lengkap' => 'Siti Aminah', 'nip' => '198001012005'])
            ->assertRedirect();

        $pegawai = Pegawai::where('sekolah_id', $this->sekolah->id)->firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('master-data.pegawai.update', $pegawai), ['nama_lengkap' => 'Siti Aminah, S.Pd'])
            ->assertRedirect();

        $this->assertSame('Siti Aminah, S.Pd', $pegawai->fresh()->nama_lengkap);

        $this->actingAs($this->operator)
            ->delete(route('master-data.pegawai.destroy', $pegawai))
            ->assertRedirect();

        $this->assertSoftDeleted('master_data_pegawai', ['id' => $pegawai->id]);
    }

    public function test_filter_dan_pencarian_inventaris(): void
    {
        $this->siapkanUnit();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.index', ['search' => 'Laptop']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('units.data', 2)->where('filter.search', 'Laptop'));

        $unit = InventarisUnit::firstOrFail();
        $unit->update(['kondisi' => 'RB']);

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.index', ['kondisi' => 'RB']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('units.data', 1));
    }

    public function test_detail_unit_menampilkan_data_dan_riwayat(): void
    {
        $this->siapkanUnit();
        $unit = InventarisUnit::firstOrFail();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.show', $unit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unit.nomor_register', $unit->nomor_register)
                ->has('mutasi', 0)
                ->has('ruangList', 0)
                ->has('pegawaiList', 0));
    }

    public function test_ubah_kondisi_dan_penempatan_unit(): void
    {
        $this->siapkanUnit();
        $unit = InventarisUnit::firstOrFail();
        $ruang = activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']));
        $pegawai = activity()->withoutLogging(fn () => Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi']));

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi', $unit), [
                'kondisi' => 'RR',
                'ruang_id' => $ruang->id,
                'pegawai_id' => $pegawai->id,
                'keterangan' => 'Layar retak',
            ])
            ->assertRedirect();

        $unit->refresh();
        $this->assertSame('RR', $unit->kondisi);
        $this->assertSame($ruang->id, $unit->ruang_id);
        $this->assertSame($pegawai->id, $unit->pegawai_id);
        $this->assertSame('Layar retak', $unit->keterangan);
    }

    public function test_kondisi_tidak_valid_ditolak(): void
    {
        $this->siapkanUnit();
        $unit = InventarisUnit::firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi', $unit), ['kondisi' => 'XX'])
            ->assertSessionHasErrors('kondisi');
    }

    public function test_ruang_sekolah_lain_ditolak_validasi(): void
    {
        $this->siapkanUnit();
        $unit = InventarisUnit::firstOrFail();

        $lain = activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain', 'kota_kab' => 'Kota Bandung']));
        $ruangLain = activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $lain->id, 'nama_ruang' => 'Ruang Lain']));

        $this->actingAs($this->operator)
            ->put(route('pencatatan-bm.inventaris.kondisi', $unit), ['kondisi' => 'B', 'ruang_id' => $ruangLain->id])
            ->assertSessionHasErrors('ruang_id');
    }

    public function test_sinkronisasi_hanya_super_admin_dan_menghasilkan_unit(): void
    {
        $realisasi = $this->siapkanUnit(belumDisetujui: true);

        $super = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super',
            'username' => 'super_flow',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $super->assignRole('super_admin');

        $this->actingAs($super)
            ->post(route('pencatatan-bm.sinkronisasi.store'), [
                'sekolah_id' => $this->sekolah->id,
                'bulan' => 3,
                'tahun' => 2026,
            ])
            ->assertRedirect();

        // Belum disetujui → tidak ada unit, tetapi log tetap tercatat.
        $this->assertSame(0, InventarisUnit::count());

        KunciLaporan::where('sekolah_id', $this->sekolah->id)->update(['status_kirim' => 'disetujui']);

        $this->actingAs($super)
            ->post(route('pencatatan-bm.sinkronisasi.store'), [
                'sekolah_id' => $this->sekolah->id,
                'bulan' => 3,
                'tahun' => 2026,
            ])
            ->assertRedirect();

        $this->assertSame(2, InventarisUnit::where('realisasi_id', $realisasi->id)->count());
    }

    private function siapkanUnit(bool $belumDisetujui = false): Realisasi
    {
        KunciLaporan::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => $belumDisetujui ? 'menunggu_approval' : 'disetujui',
        ]);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'merk_tipe' => 'Lenovo',
            'satuan' => 'UNIT',
            'volume' => 2,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 18000000,
        ]));

        if (! $belumDisetujui) {
            app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);
        }

        return $realisasi;
    }
}
