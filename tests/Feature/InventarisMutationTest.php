<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\PencatatanBm\Mutasi;
use App\Models\User;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarisMutationTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private InventarisUnit $unit;

    private Ruang $ruangA;

    private Ruang $ruangB;

    private Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = $this->buatSekolah('SMA Mutasi');
        $this->operator = $this->buatUser($this->sekolah, 'op_mutasi');
        $this->unit = $this->buatUnit($this->sekolah);

        $this->ruangA = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));
        $this->ruangB = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Lab Komputer',
        ]));
        $this->pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso',
        ]));
    }

    private function buatSekolah(string $nama): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
        ]));
    }

    private function buatUser(Sekolah $sekolah, string $username): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function buatUnit(Sekolah $sekolah, string $kode = '5.2.02.01.004'): InventarisUnit
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

    private function tempatkan(Ruang $ruang, ?Pegawai $pegawai = null): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $ruang->id,
                'pegawai_id' => $pegawai?->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();
    }

    public function test_penempatan_pertama_membuat_histori_dan_mengisi_state_unit(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruangA->id,
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-03-10',
                'keterangan' => 'Penempatan awal',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertSame($this->ruangA->id, $unit->ruang_id);
        $this->assertSame($this->pegawai->id, $unit->pegawai_id);
        $this->assertSame(InventarisUnit::STATUS_AKTIF, $unit->status);
        $this->assertSame($this->sekolah->id, $unit->sekolah_id, 'Mutasi tidak boleh mengubah sekolah pemilik.');

        $mutasi = Mutasi::where('inventaris_unit_id', $unit->id)->firstOrFail();
        $this->assertSame('penempatan', $mutasi->jenis);
        $this->assertNull($mutasi->asal_ruang_id);
        $this->assertSame($this->ruangA->id, $mutasi->tujuan_ruang_id);
        $this->assertSame('2026-03-10', $mutasi->tanggal->format('Y-m-d'));
        $this->assertSame($this->operator->id, $mutasi->user_id);
    }

    public function test_mutasi_kedua_mencatat_asal_dan_tujuan(): void
    {
        $this->tempatkan($this->ruangA);
        $kondisiAwal = $this->unit->fresh()->kondisi;

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruangB->id,
                'tanggal' => '2026-04-01',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertSame($this->ruangB->id, $unit->ruang_id);
        $this->assertSame($this->sekolah->id, $unit->sekolah_id);

        $mutasi = Mutasi::where('inventaris_unit_id', $unit->id)->latest('tanggal')->firstOrFail();
        $this->assertSame('mutasi', $mutasi->jenis);
        $this->assertSame($this->ruangA->id, $mutasi->asal_ruang_id);
        $this->assertSame($this->ruangB->id, $mutasi->tujuan_ruang_id);
        $this->assertSame($kondisiAwal, $mutasi->asal_kondisi);

        $this->assertSame(2, Mutasi::where('inventaris_unit_id', $unit->id)->count(), 'Histori lama tidak dihapus.');
    }

    public function test_mutasi_menolak_ruang_sekolah_lain(): void
    {
        $lain = $this->buatSekolah('SMA Lain');
        $ruangLain = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $lain->id, 'nama_ruang' => 'Ruang Lain',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $ruangLain->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertSessionHasErrors('ruang_id');

        $this->assertSame(0, Mutasi::count());
        $this->assertNull($this->unit->fresh()->ruang_id);
    }

    public function test_mutasi_menolak_pegawai_sekolah_lain(): void
    {
        $lain = $this->buatSekolah('SMA Lain');
        $pegawaiLain = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $lain->id, 'nama_lengkap' => 'Pegawai Lain',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'pegawai_id' => $pegawaiLain->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertSessionHasErrors('pegawai_id');
    }

    public function test_tidak_bisa_mutasi_unit_sekolah_lain_dengan_uuid(): void
    {
        $sekolahB = $this->buatSekolah('SMA B');
        $unitB = $this->buatUnit($sekolahB);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $unitB), [
                'ruang_id' => $this->ruangA->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertNotFound();

        $this->assertNull($unitB->fresh()->ruang_id);
    }

    public function test_unit_dihapus_koreksi_tidak_boleh_ditempatkan(): void
    {
        $this->unit->update(['status' => InventarisUnit::STATUS_DIHAPUS_KOREKSI]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruangA->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertSessionHasErrors('unit');

        $this->assertSame(0, Mutasi::count());
    }

    public function test_unit_dikembalikan_boleh_ditempatkan_ulang_tanpa_hapus_histori(): void
    {
        $this->tempatkan($this->ruangA);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), [
                'kondisi' => 'RR',
                'tanggal' => '2026-05-01',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $this->ruangB->id,
                'tanggal' => '2026-06-01',
            ])
            ->assertRedirect();

        $unit = $this->unit->fresh();
        $this->assertSame(InventarisUnit::STATUS_AKTIF, $unit->status);
        $this->assertSame($this->ruangB->id, $unit->ruang_id);
        $this->assertSame(3, Mutasi::where('inventaris_unit_id', $unit->id)->count());
        $this->assertSame('penempatan', Mutasi::where('inventaris_unit_id', $unit->id)->latest('tanggal')->first()->jenis);
    }
}
