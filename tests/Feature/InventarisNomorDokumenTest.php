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
 * Penomoran surat inventaris: serial per jenis per sekolah, kode klasifikasi
 * dari master sekolah, dan nomor yang dibatalkan tidak dipakai ulang.
 */
class InventarisNomorDokumenTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private InventarisUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Nomor',
            'kota_kab' => 'Kota Bandung',
            'kode_bast' => 'BAST-X',
            'kode_fakta' => 'FKI-Y',
            'kode_pengembalian' => 'KEM-Z',
        ]));

        $this->unit = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];
    }

    private function operator(): User
    {
        $operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Nomor',
            'username' => 'op_nomor',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $operator->assignRole('operator_sekolah');

        return $operator;
    }

    public function test_bast_bernomor_otomatis_dan_tidak_memakai_ulang_nomor_batal(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-12'])
            ->assertRedirect();

        $this->assertSame('001/BAST-X', Dokumen::firstOrFail()->nomor);

        // Nomor yang dibatalkan lewat Rekapan BA dilewati: serial berikutnya 002.
        $pertama = Dokumen::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('pencatatan-bm.berita-acara.destroy', $pertama), ['alasan' => 'Salah input'])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-13'])
            ->assertRedirect();

        $this->assertSame('002/BAST-X', Dokumen::whereNotNull('nomor')->latest('created_at')->firstOrFail()->nomor);
    }

    public function test_fakta_memakai_kode_klasifikasi_sendiri(): void
    {
        $operator = $this->operator();
        $pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
        ]));
        $ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
        ]));

        $this->unit->update(['ruang_id' => $ruang->id, 'pegawai_id' => $pegawai->id]);

        $this->actingAs($operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-12'])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('pencatatan-bm.dokumen.fakta-integritas', $pegawai), ['tanggal' => '2026-03-12'])
            ->assertRedirect();

        $fakta = Dokumen::where('jenis', 'fakta_integritas')->firstOrFail();

        // Bukan kode BAST: serial Fakta tidak boleh bertabrakan dengan BAST.
        $this->assertSame('001/FKI-Y', $fakta->nomor);
    }

    public function test_pengembalian_menerbitkan_berita_acara_bernomor(): void
    {
        $operator = $this->operator();
        $ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
        ]));

        $this->unit->update(['ruang_id' => $ruang->id]);

        $this->actingAs($operator)
            ->post(route('pencatatan-bm.pengembalian.store'), ['unit_ids' => [$this->unit->id],
                'kondisi' => 'RR',
                'tanggal' => '2026-05-01',
            ])
            ->assertRedirect();

        $dokumen = Dokumen::where('jenis', 'pengembalian')->firstOrFail();

        $this->assertSame('001/KEM-Z', $dokumen->nomor);
        $this->assertSame('2026-05-01', $dokumen->tanggal->toDateString());
    }
}
