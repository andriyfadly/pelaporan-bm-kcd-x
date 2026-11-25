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
 * Endpoint pendukung konsol: pratinjau nomor surat, BAST per baris (rentang
 * register), dan ekspor rekap BAST.
 */
class InventarisRekapBastTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Pegawai $pegawai;

    private Ruang $ruang;

    /** @var array<int, InventarisUnit> */
    private array $units = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Rekap',
            'kota_kab' => 'Kota Bandung',
            'kode_bast' => 'AR.03.05-CADISDIK.WIL-X',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Rekap',
            'username' => 'op_rekap',
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

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
        ]));

        $this->units = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'merk_tipe' => 'Lenovo',
            'volume' => 2,
            'tanggal_perolehan' => '2026-03-10',
        ]);

        InventarisUnit::query()->update(['ruang_id' => $this->ruang->id, 'pegawai_id' => $this->pegawai->id]);
    }

    public function test_pratinjau_nomor_surat_memakai_kode_klasifikasi(): void
    {
        $this->actingAs($this->operator)
            ->getJson(route('pencatatan-bm.penomoran.pratinjau', 'bast'))
            ->assertOk()
            ->assertJson(['nomor' => '001/AR.03.05-CADISDIK.WIL-X']);
    }

    public function test_pratinjau_nomor_surat_jenis_lain_tidak_terdefinisi(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.penomoran.pratinjau', 'lainnya'))
            ->assertNotFound();
    }

    public function test_bast_baris_membuat_satu_dokumen_untuk_rentang(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => array_map(fn (InventarisUnit $unit): string => $unit->id, $this->units),
                'tanggal' => '2026-03-12',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dokumen = Dokumen::where('jenis', 'bast')->firstOrFail();

        $this->assertCount(2, $dokumen->payload['units']);
        $this->assertSame('001/AR.03.05-CADISDIK.WIL-X', $dokumen->nomor);
    }

    public function test_bast_baris_menolak_unit_belum_disalurkan(): void
    {
        InventarisUnit::query()->update(['ruang_id' => null, 'pegawai_id' => null]);

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => [$this->units[0]->id],
                'tanggal' => '2026-03-12',
            ])
            ->assertSessionHasErrors('unit_ids');

        $this->assertSame(0, Dokumen::count());
    }

    public function test_bast_baris_menolak_unit_sekolah_lain(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Rekap Lain',
            'kota_kab' => 'Kota Bogor',
        ]));

        $unitLain = app(PendaftaranInventarisService::class)->daftarkan($lain->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop Lain',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => [$unitLain->id],
                'tanggal' => '2026-03-12',
            ])
            ->assertNotFound();
    }

    public function test_export_rekap_bast_menghasilkan_xlsx(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => [$this->units[0]->id],
                'tanggal' => '2026-03-12',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.berita-acara.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_rekap_berita_acara_menampilkan_kolom_tabel(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast-baris'), [
                'unit_ids' => [$this->units[0]->id],
                'tanggal' => '2026-03-12',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.berita-acara.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('daftar', 1)
                ->where('daftar.0.no_urut', '001')
                ->where('daftar.0.nama_barang', 'Laptop')
                ->where('daftar.0.no_reg', '001')
                ->where('daftar.0.kondisi', 'Baik')
                ->where('kodeBast', 'AR.03.05-CADISDIK.WIL-X')
                ->where('total', 1));
    }
}
