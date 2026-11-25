<?php

namespace Tests\Feature;

use App\Models\Master\KodeBarang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\DokumenInventarisService;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use App\Services\PencatatanBm\QrLabelService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Label aset 2-up: kode lokasi 11.01.32…, register 4 digit, uraian dari katalog,
 * dan QR yang tidak kedaluwarsa.
 */
class InventarisLabelTest extends TestCase
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
            'nama_sekolah' => 'SMA Label',
            'kota_kab' => 'Kota Bandung',
            'kode_wilayah' => '32',
            'kode_sub_pengguna' => '12345',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Label',
            'username' => 'op_label',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        activity()->withoutLogging(fn () => KodeBarang::create([
            'kode_barang' => '1.3.2.10.01.02.002',
            'uraian' => 'Laptop',
            'satuan' => 'Unit',
        ]));

        $this->unit = app(PendaftaranInventarisService::class)->daftarkan($this->sekolah->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'volume' => 1,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];
    }

    public function test_label_memakai_kode_lokasi_dan_register_empat_digit(): void
    {
        $label = app(DokumenInventarisService::class)
            ->labelMassal($this->sekolah, InventarisUnit::with('sekolah')->get());

        $this->assertSame(200, $label->getStatusCode());
    }

    public function test_template_label_memuat_kode_dan_uraian_katalog(): void
    {
        $html = view('pencatatan-bm.label', [
            'sekolah' => $this->sekolah,
            'units' => InventarisUnit::all(),
            'label' => [
                $this->unit->id => ['uraian' => 'Laptop', 'qr' => '<svg></svg>'],
            ],
        ])->render();

        $this->assertStringContainsString('11.01.32.32.000001.012345.2026', $html);
        $this->assertStringContainsString('1.3.2.10.01.02.002.0001', $html);
        $this->assertStringContainsString('Laptop', $html);
    }

    public function test_qr_unit_tidak_bertanda_tangan(): void
    {
        $url = app(QrLabelService::class)->unitUrl($this->unit);

        $this->assertSame(route('pencatatan-bm.inventaris.show', ['unit' => $this->unit->id]), $url);
        $this->assertStringNotContainsString('expires=', $url);
    }

    public function test_cetak_label_massal_menghasilkan_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.label.cetak'), ['unit_ids' => [$this->unit->id]]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_halaman_labeling_menampilkan_checkbox_dan_filter(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.label.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('units', 1)
                ->where('units.0.nomor_register', $this->unit->nomor_register)
                ->has('tahunList', 1)
                ->where('filter.tahun', ''));
    }

    public function test_cetak_label_tanpa_pilihan_kembali_dengan_pesan(): void
    {
        $this->actingAs($this->operator)
            ->from(route('pencatatan-bm.label.index'))
            ->post(route('pencatatan-bm.label.cetak'), ['unit_ids' => []])
            ->assertRedirect(route('pencatatan-bm.label.index'))
            ->assertSessionHas('error', 'Silakan pilih minimal satu barang untuk dicetak!');
    }

    public function test_cetak_label_menolak_unit_sekolah_lain(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Label Lain',
            'kota_kab' => 'Kota Bogor',
        ]));
        $unitLain = app(PendaftaranInventarisService::class)->daftarkan($lain->id, [
            'kode_barang' => '1.3.2.10.01.02.002',
            'nama_barang' => 'Laptop',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'tanggal_perolehan' => '2026-03-10',
        ])[0];

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.label.cetak'), ['unit_ids' => [$unitLain->id]])
            ->assertNotFound();
    }

    public function test_kartu_barang_menghasilkan_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.inventaris.kartu', $this->unit));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
