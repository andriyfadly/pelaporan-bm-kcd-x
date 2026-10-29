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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Halaman indeks mutasi/pengembalian/dokumen dan cabang penolakan yang belum
 * tersentuh test alur utama.
 */
class InventarisHalamanTest extends TestCase
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
            'nama_sekolah' => 'SMA Halaman',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Halaman',
            'username' => 'op_halaman',
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
    }

    public function test_halaman_mutasi_menampilkan_riwayat_dan_pilihan(): void
    {
        $ruang = activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru']));
        activity()->withoutLogging(fn () => Pegawai::create(['sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi']));

        // Mutasi hanya untuk unit yang sudah disalurkan.
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.store', $this->unit), [
                'ruang_id' => $ruang->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.mutasi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('riwayat.data', 1)
                ->has('units', 1)
                ->has('ruangList', 1)
                ->has('pegawaiList', 1));
    }

    public function test_mutasi_tanpa_perubahan_apa_pun_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.store', $this->unit), ['tanggal' => '2026-03-10'])
            ->assertSessionHasErrors('ruang_id');

        $this->assertSame(0, Mutasi::count());
    }

    public function test_halaman_pengembalian_menampilkan_unit_ditempatkan(): void
    {
        $ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.penyaluran.store', $this->unit), [
                'ruang_id' => $ruang->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.pengembalian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('dipinjam', 1)->has('riwayat', 0));
    }

    public function test_halaman_dokumen_menampilkan_riwayat_dokumen(): void
    {
        activity()->withoutLogging(fn () => Dokumen::create([
            'sekolah_id' => $this->sekolah->id,
            'jenis' => 'bast',
            'nomor' => 'BAST-01',
            'payload' => ['sekolah' => ['nama_sekolah' => 'SMA Halaman']],
        ]));

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dokumen', 1)
                // Prop ini dipakai bagian "Cetak Langsung"; tanpa itu UI blank.
                ->has('ruangList'));
    }

    public function test_halaman_dokumen_menyediakan_ruang_untuk_cetak_kir(): void
    {
        activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Cetak',
        ]));

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('ruangList', 1)
                ->has('ruangList.0', fn ($item) => $item->hasAll(['id', 'nama_ruang'])->etc()));
    }

    public function test_surat_pengembalian_ditolak_bila_unit_belum_dikembalikan(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.surat-pengembalian', $this->unit), ['tanggal' => '2026-05-02'])
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Dokumen::count());
    }

    public function test_sinkronisasi_store_menolak_sekolah_tidak_ada(): void
    {
        $super = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Halaman',
            'username' => 'super_halaman',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $super->assignRole('super_admin');

        $this->actingAs($super)
            ->post(route('pencatatan-bm.sinkronisasi.store'), [
                'sekolah_id' => (string) Str::uuid(),
                'bulan' => 3,
                'tahun' => 2026,
            ])
            ->assertSessionHasErrors('sekolah_id');
    }

    public function test_halaman_sinkronisasi_tanpa_sekolah_menampilkan_pilihan_sekolah(): void
    {
        $super = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Pilihan',
            'username' => 'super_pilihan',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $super->assignRole('super_admin');

        $this->actingAs($super)
            ->get(route('pencatatan-bm.sinkronisasi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sekolahList', 1));
    }
}
