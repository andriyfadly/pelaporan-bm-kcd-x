<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cetak: KIR, Buku Inventaris, Sensus, dan Rekap Penyaluran mengikuti tata
 * letak acuan (kolom, filter, dan pesan validasi).
 */
class InventarisDokumenCetakTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'sma cetak',
            'kota_kab' => 'Kota Bandung',
            'kode_pos' => '40123',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Cetak',
            'username' => 'op_cetak',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');

        $this->ruang = activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'ruang guru',
        ]));

        $pegawai = activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
        ]));

        $pendaftaran = app(PendaftaranInventarisService::class);

        foreach (['2026-03-10', '2025-07-01'] as $tanggal) {
            $pendaftaran->daftarkan($this->sekolah->id, [
                'kode_barang' => '1.3.2.10.01.02.002',
                'nama_barang' => 'Laptop',
                'merk_tipe' => 'Lenovo',
                'bahan' => 'Logam',
                'volume' => 1,
                'tanggal_perolehan' => $tanggal,
            ]);
        }

        InventarisUnit::query()->update([
            'ruang_id' => $this->ruang->id,
            'pegawai_id' => $pegawai->id,
        ]);
    }

    public function test_kir_memuat_unit_kerja_dan_ruangan(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.kir', ['ruang' => $this->ruang->id]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_kir_menerima_opsi_penanda_tangan(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.kir', [
                'ruang' => $this->ruang->id,
                'show_ks' => 1,
                'show_wks' => 1,
                'show_pj' => 1,
                'tanggal' => '2026-03-12',
            ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_buku_inventaris_tanpa_tahun_memuat_semua_tahun(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.buku-inventaris'));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_buku_inventaris_semester_menyaring_bulan_perolehan(): void
    {
        $units = fn (int $semester) => InventarisUnit::query()
            ->where('tahun_perolehan', 2026)
            ->when($semester === 1, fn ($q) => $q->whereBetween('bulan_perolehan', [1, 6]))
            ->when($semester === 2, fn ($q) => $q->whereBetween('bulan_perolehan', [7, 12]))
            ->get();

        $this->assertSame(1, $units(1)->count());
        $this->assertSame(0, $units(2)->count());

        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.buku-inventaris', ['tahun' => 2026, 'semester' => 2]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_dokumen_index_mengirim_pegawai_untuk_penanggung_jawab_kir(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.dokumen.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawaiList', 1)
                ->where('pegawaiList.0.nama_lengkap', 'Budi Santoso')
                ->has('ruangList', 1));
    }

    public function test_sensus_mengelompokkan_unit_per_pegawai(): void
    {
        $html = view('pencatatan-bm.sensus', [
            'judul' => 'Sensus Aset',
            'sekolah' => $this->sekolah,
            'search' => '',
            'units' => InventarisUnit::with(['pegawai', 'ruang'])->get(),
        ])->render();

        $this->assertStringContainsString('SENSUS BARANG MILIK DAERAH PERALATAN DAN MESIN', $html);
        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
    }

    public function test_rekap_penyaluran_tahun_sumber_wajib_pilih_tahun(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', ['filter' => 'tahun_sumber']))
            ->assertSessionHasErrors('tahun');

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', ['filter' => 'tahun_sumber', 'tahun' => 2026]))
            ->assertSessionHasErrors('asal');
    }

    public function test_rekap_penyaluran_rentang_dan_ruang_memfilter(): void
    {
        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', ['filter' => 'rentang']))
            ->assertSessionHasErrors('tahun_dari');

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', ['filter' => 'ruang']))
            ->assertSessionHasErrors('ruang');

        $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', [
                'filter' => 'rentang',
                'tahun_dari' => 2026,
                'semester_dari' => 1,
                'tahun_sampai' => 2026,
                'semester_sampai' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rekap_penyaluran_semua_data_menghasilkan_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pencatatan-bm.rekap-penyaluran.unduh', ['filter' => 'semua']));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
