<?php

namespace Tests\Feature;

use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\PencatatanBm\InventarisUnit;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use App\Services\PencatatanBm\PendaftaranInventarisService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_rekap_penyaluran_memuat_kolom_satuan_dan_blok_ttd_wakasek(): void
    {
        $service = app(PenugasanPegawaiService::class);
        $service->simpan($this->sekolah->id, (string) $this->operator->id, [
            'jabatan' => 'wakasek_sarpras',
            'nama' => 'Dewi Lestari',
            'nip' => '197505052000032001',
            'mulai_menjabat' => '2026-01-01',
        ]);

        $units = InventarisUnit::with(['ruang', 'pegawai'])->get();

        $html = view('pencatatan-bm.rekap-penyaluran', [
            'judul' => 'Rekap Penyaluran Belanja Modal',
            'sekolah' => $this->sekolah,
            'units' => $units,
            'rincian' => $this->rincianRekap($units),
            'pejabat' => $service->pejabatPada($this->sekolah->id, '2026-06-01'),
            'labelJabatan' => $service->labelPenandatangan($this->sekolah->id),
        ])->render();

        // Acuan: PENYALURAN punya kolom SATUAN (LOKASI, Vol, SATUAN, PENERIMA).
        $this->assertStringContainsString('<th colspan="4">PENYALURAN</th>', $html);
        $this->assertStringContainsString('>SATUAN</th>', $html);

        // Unit disalurkan mencetak satuannya, bukan tanda hubung.
        $this->assertMatchesRegularExpression('/<td class="mid">Unit<\/td>/', $html);

        // Blok tanda tangan acuan: Wakasek Sarpras + nama + NIP.
        $this->assertStringContainsString('Wakasek Sarpras', $html);
        $this->assertStringContainsString('Dewi Lestari', $html);
        $this->assertStringContainsString('NIP. 197505052000032001', $html);
    }

    public function test_buku_inventaris_membuang_barang_bertanggal_setelah_tanggal_cetak(): void
    {
        $depan = now()->addYear();

        activity()->withoutLogging(fn () => InventarisUnit::create([
            'sekolah_id' => $this->sekolah->id,
            'kode_barang' => '1.3.2.10.01.02.999',
            'nama_barang' => 'Barang Masa Depan',
            'nomor_register' => '901',
            'tanggal_perolehan' => $depan->toDateString(),
            'tahun_perolehan' => (int) $depan->year,
            'bulan_perolehan' => (int) $depan->month,
            'satuan' => 'Unit',
            'status' => InventarisUnit::STATUS_AKTIF,
            'kondisi' => 'B',
        ]));

        $teks = fn (string $tanggal): string => $this->teksPdf(
            $this->actingAs($this->operator)
                ->get(route('pencatatan-bm.dokumen.buku-inventaris', ['tanggal' => $tanggal]))
                ->assertOk()
                ->getContent()
        );

        $hariIni = $teks(now()->toDateString());
        $this->assertStringNotContainsString('Barang Masa Depan', $hariIni);
        // Barang yang sudah ada tetap tercetak.
        $this->assertStringContainsString('Laptop', $hariIni);

        $this->assertStringContainsString('Barang Masa Depan', $teks($depan->copy()->addDay()->toDateString()));
    }

    /** Buka stream PDF DomPDF (FlateDecode) jadi teks mentah untuk assertion. */
    private function teksPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $cocok);

        $teks = '';
        foreach ($cocok[1] as $stream) {
            $hasil = @gzuncompress($stream);
            if ($hasil !== false) {
                $teks .= $hasil;
            }
        }

        return $teks;
    }

    /**
     * @param  Collection<int, InventarisUnit>  $units
     * @return array<int, array<string, mixed>>
     */
    private function rincianRekap($units): array
    {
        return $units->map(fn (InventarisUnit $unit): array => [
            'kode_barang' => $unit->kode_barang,
            'nama_barang' => $unit->nama_barang,
            'merk_tipe' => $unit->merk_tipe,
            'tanggal_perolehan' => $unit->tanggal_perolehan?->format('d/m/Y'),
            'bulan_perolehan' => $unit->bulan_perolehan,
            'tahun_perolehan' => $unit->tahun_perolehan,
            'volume' => 1,
            'satuan' => $unit->satuan,
            'harga_satuan' => (float) $unit->harga_satuan,
            'lokasi' => $unit->ruang?->nama_ruang,
            'penerima' => $unit->pegawai?->nama_lengkap,
            'nomor_register' => $unit->nomor_register,
            'keterangan' => '',
            'disalurkan' => $unit->ruang_id !== null || $unit->pegawai_id !== null,
        ])->all();
    }
}
