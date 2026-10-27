<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
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

/**
 * Temuan review akhir: perubahan yang tidak disebut tidak boleh mengosongkan
 * penempatan, dan pesan "perlu tinjau manual" harus sampai ke pengguna.
 */
class InventarisReviewFixTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private InventarisUnit $unit;

    private Ruang $ruang;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Review',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Review',
            'username' => 'op_review',
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

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));
        $this->pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruang->id,
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();
    }

    public function test_mutasi_kondisi_saja_tidak_mengosongkan_ruang_dan_pegawai(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'kondisi' => 'RR',
                'tanggal' => '2026-04-01',
                'keterangan' => 'Layar retak',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertSame('RR', $unit->kondisi);
        $this->assertSame($this->ruang->id, $unit->ruang_id, 'Ruang tidak disebut, jadi harus tetap.');
        $this->assertSame($this->pegawai->id, $unit->pegawai_id, 'Pegawai tidak disebut, jadi harus tetap.');
    }

    public function test_null_eksplisit_tetap_mengosongkan_penempatan(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => null,
                'pegawai_id' => null,
                'kondisi' => 'B',
                'tanggal' => '2026-04-01',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertNull($unit->ruang_id);
        $this->assertNull($unit->pegawai_id);
        $this->assertSame(2, Mutasi::where('inventaris_unit_id', $unit->id)->count());
    }

    public function test_pesan_perlu_tinjau_manual_sampai_ke_flash_frontend(): void
    {
        // Volume turun pada unit yang sudah dimutasi → perlu review.
        Realisasi::firstOrFail()->update(['volume' => 0, 'nilai_perolehan' => 0]);

        $admin = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin Review',
            'username' => 'admin_review',
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $admin->assignRole('admin_kcd');

        KunciLaporan::where('sekolah_id', $this->sekolah->id)->update(['status_kirim' => 'menunggu_approval']);

        $this->actingAs($admin)
            ->post(route('pelaporan-bm.kunci-laporan.status'), [
                'sekolah_id' => $this->sekolah->id,
                'bulan' => 3,
                'tahun' => 2026,
                'status_kirim' => 'disetujui',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(
            InventarisUnit::STATUS_AKTIF,
            $this->unit->fresh()->status,
            'Unit bermutasi tetap aktif menunggu tinjauan manual.',
        );
    }

    public function test_sync_log_gate_belum_disetujui_tidak_dicatat_sukses(): void
    {
        KunciLaporan::where('sekolah_id', $this->sekolah->id)->update(['status_kirim' => 'draft']);

        $realisasi = Realisasi::firstOrFail();
        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);

        // SyncLog memakai UUIDv7 (time-ordered), jadi latest('id') kronologis
        // walau created_at dua log bisa sama detik.
        $log = SyncLog::latest('id')->firstOrFail();

        $this->assertSame('dilewati', $log->status, 'Periode belum disetujui bukan sinkronisasi sukses.');
        $this->assertSame(0, $log->dibuat);
    }
}
