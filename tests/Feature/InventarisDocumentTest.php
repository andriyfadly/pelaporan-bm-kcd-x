<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PencatatanBm\Dokumen;
use App\Models\PencatatanBm\Foto;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\QrLabelService;
use App\Services\PencatatanBm\SinkronisasiInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventarisDocumentTest extends TestCase
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
            'nama_sekolah' => 'SMA Dokumen',
            'kota_kab' => 'Kota Bandung',
            'npsn' => '20246369',
            'alamat' => 'Jl. Contoh 1',
            'telepon' => '022123456',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Dokumen',
            'username' => 'op_dokumen',
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
            'merk_tipe' => 'Lenovo',
            'no_spk' => 'SPK-01',
            'ba_no' => 'BA-01',
            'ba_tgl' => '2026-03-05',
            'volume' => 1,
            'harga_satuan' => 9000000,
            'nilai_perolehan' => 9000000,
        ]));

        app(SinkronisasiInventarisService::class)->syncRealisasi($realisasi);
        $this->unit = InventarisUnit::firstOrFail();

        $ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id, 'nama_ruang' => 'Ruang Guru',
        ]));
        activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id, 'nama_lengkap' => 'Budi Santoso', 'nip' => '19800101',
        ]));

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.mutasi.store', $this->unit), [
                'ruang_id' => $ruang->id,
                'tanggal' => '2026-03-10',
            ])
            ->assertRedirect();
    }

    public function test_kir_ruangan_pdf_memakai_data_target_dan_snapshot(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.kir', ['ruang' => Ruang::firstOrFail()->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_kir_tidak_bisa_membaca_ruang_sekolah_lain(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain', 'kota_kab' => 'Kota Bandung']));
        $ruangLain = activity()->withoutLogging(fn () => Ruang::create(['sekolah_id' => $lain->id, 'nama_ruang' => 'Ruang Lain']));

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.kir', ['ruang' => $ruangLain->id]))
            ->assertNotFound();
    }

    public function test_buku_inventaris_dan_sensus_pdf(): void
    {
        foreach (['pencatatan-bm.dokumen.buku-inventaris', 'pencatatan-bm.dokumen.sensus'] as $route) {
            $response = $this->actingAs($this->operator)->get(route($route, ['tahun' => 2026]));
            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent(), "Route {$route} tidak menghasilkan PDF.");
        }
    }

    public function test_bast_menyimpan_snapshot_dokumen(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), [
                'tanggal' => '2026-03-12',
                'nomor' => 'BAST-01',
            ])
            ->assertRedirect();

        $dokumen = Dokumen::where('sekolah_id', $this->sekolah->id)->where('jenis', 'bast')->firstOrFail();

        $this->assertSame('BAST-01', $dokumen->nomor);
        $this->assertSame($this->unit->id, $dokumen->inventaris_unit_id);
        $this->assertSame('SMA Dokumen', $dokumen->payload['sekolah']['nama_sekolah']);
        $this->assertSame('Laptop', $dokumen->payload['unit']['nama_barang']);
        $this->assertSame('Ruang Guru', $dokumen->payload['unit']['ruang']);

        // Mengubah data unit tidak mengubah snapshot dokumen lama.
        $this->unit->update(['nama_barang' => 'Laptop Baru']);
        $this->assertSame('Laptop', $dokumen->fresh()->payload['unit']['nama_barang']);
    }

    public function test_fakta_integritas_berdasarkan_pegawai_penerima(): void
    {
        $pegawai = Pegawai::firstOrFail();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.fakta-integritas', $pegawai), ['tanggal' => '2026-03-12'])
            ->assertRedirect();

        $dokumen = Dokumen::where('jenis', 'fakta_integritas')->firstOrFail();
        $this->assertSame($pegawai->id, $dokumen->inventaris_pegawai_id);
        $this->assertSame('Budi Santoso', $dokumen->payload['pegawai']['nama_lengkap']);
    }

    public function test_surat_pengembalian_membaca_histori_terbaru(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.pengembalian.store', $this->unit), [
                'kondisi' => 'RR',
                'tanggal' => '2026-05-01',
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.surat-pengembalian', $this->unit), ['tanggal' => '2026-05-02'])
            ->assertRedirect();

        $dokumen = Dokumen::where('jenis', 'surat_pengembalian')->firstOrFail();
        $this->assertSame('RR', $dokumen->payload['mutasi']['tujuan_kondisi']);
        $this->assertSame('2026-05-01', $dokumen->payload['mutasi']['tanggal']);
    }

    public function test_cetak_dokumen_tersimpan_bisa_diunduh_sebagai_pdf(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-12', 'nomor' => 'BAST-01'])
            ->assertRedirect();

        $dokumen = Dokumen::firstOrFail();

        $response = $this->actingAs($this->operator)->get(route('pencatatan-bm.dokumen.pdf', $dokumen));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_dokumen_sekolah_lain_tidak_bisa_diunduh(): void
    {
        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.dokumen.bast', $this->unit), ['tanggal' => '2026-03-12', 'nomor' => 'BAST-01'])
            ->assertRedirect();

        $dokumen = Dokumen::firstOrFail();
        $lain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain',
            'username' => 'op_lain_dok',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain 2', 'kota_kab' => 'Kota Bandung']))->id,
            'password_changed_at' => now(),
        ]));
        $lain->assignRole('operator_sekolah');

        $this->actingAs($lain)->get(route('pencatatan-bm.dokumen.pdf', $dokumen))->assertNotFound();
    }

    public function test_upload_foto_valid_tersimpan_dengan_metadata(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.foto', $this->unit), [
                'foto' => UploadedFile::fake()->image('unit.jpg', 800, 600)->size(200),
            ])
            ->assertRedirect();

        $foto = Foto::firstOrFail();
        $this->assertSame($this->unit->id, $foto->inventaris_unit_id);
        $this->assertSame($this->sekolah->id, $foto->sekolah_id);
        $this->assertSame('image/jpeg', $foto->mime);
        $this->assertSame($this->operator->id, $foto->uploaded_by);
        $this->assertSame(64, strlen($foto->checksum));
        Storage::disk('public')->assertExists($foto->path);

        $this->assertStringNotContainsString('unit.jpg', $foto->path, 'Nama file harus dibuat server-side.');
    }

    public function test_upload_foto_menolak_berkas_bukan_gambar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.foto', $this->unit), [
                'foto' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Foto::count());
    }

    public function test_upload_foto_menolak_berkas_terlalu_besar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.foto', $this->unit), [
                'foto' => UploadedFile::fake()->image('besar.jpg')->size(6000),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Foto::count());
    }

    public function test_unduh_foto_terbatas_pada_tenant_pemilik(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator)
            ->post(route('pencatatan-bm.inventaris.foto', $this->unit), [
                'foto' => UploadedFile::fake()->image('unit.jpg'),
            ])
            ->assertRedirect();

        $foto = Foto::firstOrFail();

        $this->actingAs($this->operator)->get(route('pencatatan-bm.foto.show', $foto))->assertOk();

        $lain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain 3',
            'username' => 'op_lain_foto',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain 3', 'kota_kab' => 'Kota Bandung']))->id,
            'password_changed_at' => now(),
        ]));
        $lain->assignRole('operator_sekolah');

        $this->actingAs($lain)->get(route('pencatatan-bm.foto.show', $foto))->assertNotFound();
    }

    public function test_qr_unit_berisi_signed_url_dan_hanya_untuk_tenant_pemilik(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.label.qr', $this->unit))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $lain = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Lain 4',
            'username' => 'op_lain_qr',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => activity()->withoutLogging(fn () => Sekolah::create(['nama_sekolah' => 'SMA Lain 4', 'kota_kab' => 'Kota Bandung']))->id,
            'password_changed_at' => now(),
        ]));
        $lain->assignRole('operator_sekolah');

        $this->actingAs($lain)->get(route('pencatatan-bm.label.qr', $this->unit))->assertNotFound();
    }

    public function test_label_pdf_unit(): void
    {
        $response = $this->actingAs($this->operator)->get(route('pencatatan-bm.label.pdf', $this->unit));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_payload_qr_hanya_berisi_signed_route_unit(): void
    {
        $url = app(QrLabelService::class)->signedUnitUrl($this->unit);

        $this->assertStringContainsString('/pencatatan-bm/inventaris/'.$this->unit->id, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('sekolah_id=', $url);
    }
}
