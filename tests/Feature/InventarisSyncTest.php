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
use App\Services\PencatatanBm\SyncResult;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventarisSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMA Negeri 1'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function realisasi(Sekolah $sekolah, float $volume = 2, string $kode = '5.2.02.01.004'): Realisasi
    {
        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => $kode,
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'merk_tipe' => 'Lenovo ThinkPad',
            'satuan' => 'UNIT',
            'ba_no' => 'BA-01',
            'ba_tgl' => '2026-03-05',
            'volume' => $volume,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => $volume * 9000000,
        ]));
    }

    private function kunciStatus(Sekolah $sekolah, string $status, int $bulan = 3): KunciLaporan
    {
        return KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => $bulan,
            'tahun' => 2026,
            'status_kirim' => $status,
        ]);
    }

    private function service(): SinkronisasiInventarisService
    {
        return app(SinkronisasiInventarisService::class);
    }

    public function test_status_disetujui_membuat_n_unit_individual(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 3);

        $hasil = $this->service()->syncRealisasi($realisasi);

        $this->assertSame(3, $hasil->dibuat);
        $this->assertSame(0, $hasil->tetap);
        $this->assertSame(0, $hasil->perluReview);

        $units = InventarisUnit::orderBy('unit_index')->get();
        $this->assertCount(3, $units);
        $this->assertSame([1, 2, 3], $units->pluck('unit_index')->all());
        $this->assertSame(['001', '002', '003'], $units->pluck('nomor_register')->all());
        $this->assertEqualsCanonicalizing([$sekolah->id], $units->pluck('sekolah_id')->unique()->all());
        $this->assertSame('Laptop', $units->first()->nama_barang);
        $this->assertSame('Lenovo ThinkPad', $units->first()->merk_tipe);
        $this->assertSame('5.2.02.01.004', $units->first()->kode_barang);
        $this->assertSame(9000000.0, (float) $units->first()->nilai_perolehan_unit);
        $this->assertSame(3, $units->first()->bulan_perolehan);
        $this->assertSame('2026-03-05', $units->first()->tanggal_perolehan?->format('Y-m-d'));
        $this->assertSame('B', $units->first()->kondisi);
        $this->assertSame('aktif', $units->first()->status);
    }

    public function test_status_belum_disetujui_tidak_membuat_unit(): void
    {
        foreach (['draft', 'menunggu_approval'] as $status) {
            $sekolah = $this->sekolah("Sekolah {$status}");
            $this->kunciStatus($sekolah, $status);
            $realisasi = $this->realisasi($sekolah);

            $hasil = $this->service()->syncRealisasi($realisasi);

            $this->assertSame(0, $hasil->dibuat, "Status {$status} tidak boleh membuat unit.");
            $this->assertSame(0, InventarisUnit::where('realisasi_id', $realisasi->id)->count());
        }
    }

    public function test_tanpa_kunci_laporan_tidak_membuat_unit(): void
    {
        $sekolah = $this->sekolah();
        $realisasi = $this->realisasi($sekolah);

        $hasil = $this->service()->syncRealisasi($realisasi);

        $this->assertSame(0, $hasil->dibuat);
        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_kunci_laporan_bulan_lain_tidak_membuka_gate(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui', 4);
        $realisasi = $this->realisasi($sekolah);

        $this->assertSame(0, $this->service()->syncRealisasi($realisasi)->dibuat);
    }

    public function test_rerun_tidak_menduplikasi_unit(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 2);

        $this->service()->syncRealisasi($realisasi);
        $kedua = $this->service()->syncRealisasi($realisasi->fresh());

        $this->assertSame(0, $kedua->dibuat);
        $this->assertSame(2, $kedua->tetap);
        $this->assertSame(2, InventarisUnit::count());
        $this->assertEqualsCanonicalizing(['001', '002'], InventarisUnit::pluck('nomor_register')->all());
    }

    public function test_volume_bertambah_hanya_menambah_selisih_dan_register_melanjutkan(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 2);
        $this->service()->syncRealisasi($realisasi);

        $realisasi->update(['volume' => 4, 'nilai_perolehan' => 4 * 9000000]);
        $hasil = $this->service()->syncRealisasi($realisasi->fresh());

        $this->assertSame(2, $hasil->dibuat);
        $this->assertSame(2, $hasil->tetap);
        $this->assertSame(
            ['001', '002', '003', '004'],
            InventarisUnit::orderBy('unit_index')->pluck('nomor_register')->all()
        );
    }

    public function test_volume_turun_menandai_surplus_tanpa_menghapus_unit_belum_dimutasi(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 3);
        $this->service()->syncRealisasi($realisasi);

        $realisasi->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        $hasil = $this->service()->syncRealisasi($realisasi->fresh());

        $this->assertSame(0, $hasil->dibuat);
        $this->assertSame(1, $hasil->tetap);
        $this->assertSame(2, $hasil->surplus);
        $this->assertSame(0, $hasil->perluReview);
        $this->assertSame(1, InventarisUnit::where('status', 'aktif')->count());
        $this->assertSame(2, InventarisUnit::where('status', 'dihapus_koreksi')->count());
        $this->assertSame(3, InventarisUnit::count(), 'Unit surplus tidak boleh hard-delete.');
    }

    public function test_volume_turun_pada_unit_bermutasi_masuk_review_manual(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 3);
        $this->service()->syncRealisasi($realisasi);

        $bermutasi = InventarisUnit::where('unit_index', 3)->firstOrFail();
        Mutasi::create([
            'sekolah_id' => $sekolah->id,
            'inventaris_unit_id' => $bermutasi->id,
            'jenis' => 'mutasi',
            'tujuan_kondisi' => 'RR',
            'tanggal' => '2026-03-20',
        ]);

        $realisasi->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        $hasil = $this->service()->syncRealisasi($realisasi->fresh());

        $this->assertSame(1, $hasil->tetap);
        $this->assertSame(1, $hasil->surplus);
        $this->assertSame(1, $hasil->perluReview);
        $this->assertSame(3, InventarisUnit::count());
        $this->assertSame(2, InventarisUnit::where('status', 'aktif')->count(), 'Unit bermutasi tetap aktif dan menunggu review.');
        $this->assertSame(1, InventarisUnit::where('status', 'dihapus_koreksi')->count());
    }

    public function test_volume_pecahan_dibulatkan_ke_bawah(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 2.5);

        $this->assertSame(2, $this->service()->syncRealisasi($realisasi)->dibuat);
    }

    public function test_register_berlanjut_per_kode_barang_dan_sekolah(): void
    {
        $sekolah = $this->sekolah();
        $lain = $this->sekolah('SMA Negeri 2');
        $this->kunciStatus($sekolah, 'disetujui');

        $this->service()->syncRealisasi($this->realisasi($sekolah, 2, '5.2.02.01.004'));

        // Kode barang berbeda → register mulai dari 001 lagi.
        $this->service()->syncRealisasi($this->realisasi($sekolah, 1, '5.2.02.02.001'));
        $this->assertSame(
            ['001'],
            InventarisUnit::where('kode_barang', '5.2.02.02.001')->pluck('nomor_register')->all()
        );

        // Sekolah lain → register mulai dari 001 lagi, sekalipun kode barang sama.
        $kunciLain = KunciLaporan::create([
            'sekolah_id' => $lain->id, 'bulan' => 3, 'tahun' => 2026, 'status_kirim' => 'disetujui',
        ]);
        $this->assertSame('disetujui', $kunciLain->status_kirim);
        $this->service()->syncRealisasi($this->realisasi($lain, 1, '5.2.02.01.004'));

        $this->assertSame(
            ['001'],
            InventarisUnit::where('sekolah_id', $lain->id)->pluck('nomor_register')->all()
        );
    }

    public function test_sequence_tidak_pernah_dipakai_ulang_setelah_unit_dikoreksi(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 2);
        $this->service()->syncRealisasi($realisasi);

        $realisasi->update(['volume' => 1, 'nilai_perolehan' => 9000000]);
        $this->service()->syncRealisasi($realisasi->fresh());

        $realisasi->update(['volume' => 3, 'nilai_perolehan' => 3 * 9000000]);
        $hasil = $this->service()->syncRealisasi($realisasi->fresh());

        $this->assertSame(1, $hasil->dibuat);
        $this->assertSame('003', InventarisUnit::where('unit_index', 3)->value('nomor_register'));
    }

    public function test_sync_log_mencatat_hasil(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'disetujui');
        $realisasi = $this->realisasi($sekolah, 2);

        $this->service()->syncRealisasi($realisasi);

        $log = SyncLog::firstOrFail();
        $this->assertSame('sukses', $log->status);
        $this->assertSame(2, $log->dibuat);
        $this->assertSame($realisasi->id, $log->realisasi_id);
        $this->assertSame($sekolah->id, $log->sekolah_id);
        $this->assertNotNull($log->finished_at);
    }

    public function test_sync_menolak_realisasi_tanpa_sekolah(): void
    {
        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'bulan_realisasi' => 3,
            'tahun' => 2026,
            'kode_barang' => '5.2.02.01.004',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        $hasil = $this->service()->syncRealisasi($realisasi);

        $this->assertSame(0, $hasil->dibuat);
        $this->assertSame(0, InventarisUnit::count());
    }

    public function test_approval_lewat_controller_memicu_sinkronisasi(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'menunggu_approval');
        $this->realisasi($sekolah, 2);

        $admin = User::factory()->create();
        $admin->assignRole('admin_kcd');

        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.status'), [
            'sekolah_id' => $sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ])->assertRedirect();

        $this->assertSame(2, InventarisUnit::where('sekolah_id', $sekolah->id)->count());
        $this->assertSame(1, SyncLog::where('sekolah_id', $sekolah->id)->count(), 'Satu sinkronisasi periode = satu log.');
    }

    public function test_approval_gagal_sinkronisasi_tidak_membatalkan_status(): void
    {
        $sekolah = $this->sekolah();
        $this->kunciStatus($sekolah, 'menunggu_approval');
        $realisasi = $this->realisasi($sekolah, 3);

        // Simulasi alokasi register yang bentrok (mis. dua proses bersamaan):
        // sequence dipaksa mulai dari 1 padahal register '002' sudah terpakai.
        $lain = $this->realisasi($sekolah, 1, $realisasi->kode_barang);
        activity()->withoutLogging(fn () => $lain->update(['bulan_realisasi' => 4]));
        InventarisUnit::create([
            'sekolah_id' => $sekolah->id,
            'realisasi_id' => $lain->id,
            'unit_index' => 1,
            'kode_barang' => $lain->kode_barang,
            'nomor_register' => '002',
            'harga_satuan' => 0,
            'nilai_perolehan_unit' => 0,
        ]);
        DB::table('inventaris_register_sequences')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $sekolah->id,
            'kode_barang' => $realisasi->kode_barang,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin_kcd');

        $this->actingAs($admin)->post(route('pelaporan-bm.kunci-laporan.status'), [
            'sekolah_id' => $sekolah->id,
            'bulan' => 3,
            'tahun' => 2026,
            'status_kirim' => 'disetujui',
        ])->assertRedirect();

        $this->assertSame(
            'disetujui',
            KunciLaporan::where('sekolah_id', $sekolah->id)->where('bulan', 3)->value('status_kirim'),
            'Status persetujuan tidak boleh dibatalkan hanya karena sinkronisasi gagal.'
        );

        $this->assertSame(
            0,
            InventarisUnit::where('realisasi_id', $realisasi->id)->count(),
            'Sinkronisasi yang gagal harus rollback seluruh unit realisasi itu.'
        );

        $log = SyncLog::where('realisasi_id', $realisasi->id)->latest('created_at')->firstOrFail();
        $this->assertSame('gagal', $log->status);
        $this->assertNotNull($log->error);
    }

    public function test_result_dto_menyatakan_apakah_ada_perubahan(): void
    {
        $this->assertTrue((new SyncResult(dibuat: 1))->adaPerubahan());
        $this->assertTrue((new SyncResult(surplus: 1))->adaPerubahan());
        $this->assertTrue((new SyncResult(perluReview: 1))->adaPerubahan());
        $this->assertFalse((new SyncResult(tetap: 2))->adaPerubahan());
    }
}
