<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\PencatatanBm\SyncLog;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarisCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private Realisasi $realisasi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Koreksi',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        KunciLaporan::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ]);

        $this->realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $this->sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 3,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 27000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi);
    }

    private function operator(): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Koreksi',
            'username' => 'op_koreksi',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_koreksi_harga_dan_nama_menyegarkan_unit_belum_dimutasi(): void
    {
        $this->realisasi->update(['nama_barang' => 'Laptop Pro', 'harga_satuan' => 10000000]);

        $hasil = app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi->fresh());

        $this->assertSame(0, $hasil->dibuat);
        $this->assertSame(3, $hasil->tetap);
        $this->assertSame('Laptop Pro', InventarisUnit::firstOrFail()->nama_barang);
        $this->assertSame(10000000.0, (float) InventarisUnit::firstOrFail()->nilai_perolehan_unit);
    }

    public function test_unit_yang_sudah_dimutasi_tidak_ikut_tersegarkan(): void
    {
        $unit = InventarisUnit::where('unit_index', 1)->firstOrFail();
        Mutasi::create([
            'sekolah_id' => $this->sekolah->id,
            'inventaris_unit_id' => $unit->id,
            'jenis' => 'penyaluran',
            'tujuan_kondisi' => 'B',
            'tanggal' => '2026-03-10',
        ]);

        $this->realisasi->update(['merk_tipe' => 'HP Baru']);
        app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi->fresh());

        $this->assertSame('Lenovo', $unit->fresh()->merk_tipe, 'Unit bermutasi mempertahankan snapshot lamanya.');
    }

    public function test_pengurangan_volume_tidak_menghapus_unit_bermutasi_dan_dilaporkan(): void
    {
        // Unit 3 sudah dipindah; unit 2 belum. Volume turun ke 1.
        $bermutasi = InventarisUnit::where('unit_index', 3)->firstOrFail();
        Mutasi::create([
            'sekolah_id' => $this->sekolah->id,
            'inventaris_unit_id' => $bermutasi->id,
            'jenis' => 'penyaluran',
            'tujuan_kondisi' => 'B',
            'tanggal' => '2026-03-10',
        ]);

        $this->realisasi->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        $hasil = app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi->fresh());

        $this->assertSame(1, $hasil->tetap);
        $this->assertSame(1, $hasil->surplus);
        $this->assertSame(1, $hasil->perluReview);

        $this->assertSame(InventarisUnit::STATUS_AKTIF, $bermutasi->fresh()->status);
        $this->assertSame(
            InventarisUnit::STATUS_DIHAPUS_KOREKSI,
            InventarisUnit::where('unit_index', 2)->value('status'),
        );
        $this->assertSame(3, InventarisUnit::count(), 'Tidak ada hard delete.');
    }

    public function test_command_resync_dry_run_tidak_mengubah_data(): void
    {
        $this->realisasi->update(['volume' => 5, 'nilai_perolehan' => 45000000]);

        $this->artisan('pencatatan-bm:resync', [
            '--bulan' => 3, '--tahun' => 2026, '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(3, InventarisUnit::count(), 'Dry run tidak boleh membuat unit.');
    }

    public function test_command_resync_menjalankan_sinkronisasi_dan_melaporkan(): void
    {
        $this->realisasi->update(['volume' => 5, 'nilai_perolehan' => 45000000]);

        $this->artisan('pencatatan-bm:resync', ['--bulan' => 3, '--tahun' => 2026])
            ->expectsOutputToContain('dibuat: 2')
            ->assertSuccessful();

        $this->assertSame(5, InventarisUnit::where('status', 'aktif')->count());
    }

    public function test_command_resync_butuh_bulan_dan_tahun(): void
    {
        $this->artisan('pencatatan-bm:resync')->assertFailed();
    }

    public function test_halaman_sinkronisasi_menampilkan_unit_perlu_review(): void
    {
        $bermutasi = InventarisUnit::where('unit_index', 3)->firstOrFail();
        Mutasi::create([
            'sekolah_id' => $this->sekolah->id,
            'inventaris_unit_id' => $bermutasi->id,
            'jenis' => 'penyaluran',
            'tujuan_kondisi' => 'B',
            'tanggal' => '2026-03-10',
        ]);
        $this->realisasi->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        app(SinkronisasiInventarisService::class)->syncRealisasi($this->realisasi->fresh());

        $super = activity()->withoutLogging(fn () => User::create([
            'name' => 'Super Koreksi',
            'username' => 'super_koreksi',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $super->assignRole('super_admin');

        $this->actingAs($super)
            ->get(route('pencatatan-bm.sinkronisasi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs', 2)
                ->has('perluReview', 1)
                ->where('perluReview.0.nomor_register', $bermutasi->nomor_register));
    }

    public function test_review_log_tersimpan_dengan_jumlah_perlu_review(): void
    {
        $log = SyncLog::latest('created_at')->firstOrFail();

        $this->assertSame('sukses', $log->status);
        $this->assertSame(3, $log->dibuat);
        $this->assertSame(0, $log->perlu_review);
    }

    public function test_export_excel_inventaris(): void
    {
        $response = $this->actingAs($this->operator())
            ->get(route('pencatatan-bm.inventaris.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
        );
    }
}
