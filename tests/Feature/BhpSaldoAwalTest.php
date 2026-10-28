<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Barang;
use App\Models\DistribusiBhp\Penerimaan;
use App\Models\DistribusiBhp\PenerimaanDetail;
use App\Models\DistribusiBhp\Pengajuan;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\StokService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BhpSaldoAwalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN Saldo Awal'): Sekolah
    {
        return Sekolah::create(['nama_sekolah' => $nama, 'kota_kab' => 'Kota Bandung']);
    }

    private function operator(Sekolah $sekolah, string $username = 'op.saldo'): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    private function barang(string $sumberDana = 'BOS', string $nama = 'Kertas HVS', ?string $kode = null): Barang
    {
        return Barang::create([
            'kode_barang' => $kode ?? '1.1.7.09.'.substr(md5($nama), 0, 6),
            'nama_barang' => $nama,
            'satuan' => 'Rim',
            'sumber_dana' => $sumberDana,
        ]);
    }

    public function test_operator_dapat_membuka_halaman_saldo_awal(): void
    {
        $this->actingAs($this->operator($this->sekolah()))
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/SaldoAwal/Index')
                ->where('sumberDana', 'BOS')
                ->where('periode', 2026)
                ->has('items', 0)
                ->where('stats.total_nilai', 0)
            );
    }

    public function test_bendahara_tanpa_permission_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.saldo',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->get(route('distribusi-bhp.bos.saldo-awal.index'))
            ->assertForbidden();
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('distribusi-bhp.bos.saldo-awal.index'))->assertRedirect(route('login'));
    }

    public function test_operator_dapat_menambah_saldo_awal_dan_melihat_statistik(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 45000],
                ],
            ])
            ->assertRedirect(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]));

        $this->assertSame(1, Penerimaan::count());
        $this->assertTrue(Penerimaan::firstOrFail()->is_saldo_awal);

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('stats.total_barang', 1)
                ->where('stats.total_unit', 30)
                ->where('stats.total_nilai', 1_350_000)
            );
    }

    public function test_store_saldo_awal_wajib_periode(): void
    {
        $barang = $this->barang();

        $this->actingAs($this->operator($this->sekolah()))
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertSessionHasErrors('periode_tahun');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_store_saldo_awal_menolak_barang_beda_sumber_dana(): void
    {
        $barang = $this->barang('BOPD');

        $this->actingAs($this->operator($this->sekolah()))
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_store_saldo_awal_duplikat_ditolak(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang();

        $payload = [
            'sumber_dana' => 'BOS',
            'periode_tahun' => 2026,
            'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
        ];

        $this->actingAs($operator)->post(route('distribusi-bhp.bos.saldo-awal.store'), $payload)->assertRedirect();
        $this->actingAs($operator)->post(route('distribusi-bhp.bos.saldo-awal.store'), $payload)->assertSessionHasErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_store_saldo_awal_periode_lain_untuk_barang_sama_diizinkan(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2025,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 7, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Saldo awal tiap tahun adalah penambahan stok tersendiri.
        $this->assertSame(2, Penerimaan::count());
        $this->assertSame(12, app(StokService::class)->fisik($barang));
    }

    public function test_halaman_hanya_menampilkan_saldo_awal_sekolah_sendiri(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operatorA = $this->operator($sekolahA, 'op.aa');
        $operatorB = $this->operator($sekolahB, 'op.bb');
        $barang = $this->barang();

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 7, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        // Operator A melihat 1 baris; operator B melihat 0.
        $this->actingAs($operatorA)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertInertia(fn ($page) => $page->has('items', 1));

        $this->actingAs($operatorB)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertInertia(fn ($page) => $page->has('items', 0));
    }

    public function test_hapus_satu_barang_saldo_awal_mengurangi_stok(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 12, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $detail = PenerimaanDetail::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
            ->assertRedirect();

        $this->assertSame(0, Penerimaan::count());
        $this->assertSame(0, PenerimaanDetail::count());
    }

    public function test_hapus_satu_barang_tidak_menghapus_barang_lain_pada_header_sama(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $kertas = $this->barang('BOS', 'Kertas HVS');
        $pulpen = $this->barang('BOS', 'Pulpen Biru');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $kertas->id, 'jumlah' => 20, 'harga_satuan' => 50000],
                    ['barang_id' => $pulpen->id, 'jumlah' => 100, 'harga_satuan' => 3000],
                ],
            ])
            ->assertRedirect();

        // Satu header menampung kedua barang.
        $this->assertSame(1, Penerimaan::count());
        $this->assertSame(2, PenerimaanDetail::count());

        $detailKertas = PenerimaanDetail::where('barang_id', $kertas->id)->firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detailKertas))
            ->assertRedirect();

        // Header dan barang lain harus tetap ada.
        $this->assertSame(1, Penerimaan::count(), 'Header saldo awal tidak boleh terhapus saat hanya satu barang dihapus.');
        $this->assertSame(1, PenerimaanDetail::count());
        $this->assertTrue(PenerimaanDetail::where('barang_id', $pulpen->id)->exists());
        $this->assertFalse(PenerimaanDetail::where('barang_id', $kertas->id)->exists());

        // Statistik halaman konsisten dengan sisa data.
        $this->actingAs($operator)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('stats.total_barang', 1)
                ->where('stats.total_unit', 100)
                ->where('stats.total_nilai', 300_000)
            );
    }

    public function test_header_saldo_awal_terhapus_saat_barang_terakhir_dihapus(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $kertas = $this->barang('BOS', 'Kertas HVS');
        $pulpen = $this->barang('BOS', 'Pulpen Biru');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [
                    ['barang_id' => $kertas->id, 'jumlah' => 20, 'harga_satuan' => 50000],
                    ['barang_id' => $pulpen->id, 'jumlah' => 100, 'harga_satuan' => 3000],
                ],
            ])
            ->assertRedirect();

        foreach ([$kertas, $pulpen] as $barang) {
            $detail = PenerimaanDetail::where('barang_id', $barang->id)->firstOrFail();
            $this->actingAs($operator)
                ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
                ->assertRedirect();
        }

        $this->assertSame(0, PenerimaanDetail::count());
        $this->assertSame(0, Penerimaan::count(), 'Header yatim harus dibersihkan setelah detail terakhir dihapus.');
    }

    public function test_saldo_awal_yang_sudah_dipakai_pengajuan_aktif_tidak_bisa_dihapus(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang('BOS', 'Kertas HVS');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 20, 'harga_satuan' => 50000]],
            ])
            ->assertRedirect();

        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOS',
            'status' => 'submitted',
        ]);
        $pengajuan->details()->create([
            'barang_id' => $barang->id,
            'jumlah_diminta' => 5,
            'jumlah_direservasi' => 5,
        ]);

        $detail = PenerimaanDetail::firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
            ->assertSessionHas('error');

        $this->assertSame(1, PenerimaanDetail::count());
    }

    public function test_saldo_awal_tidak_terkunci_pengajuan_sumber_dana_berbeda(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barangBos = $this->barang('BOS', 'Kertas HVS');
        $barangBopd = $this->barang('BOPD', 'Barang BOPD');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barangBos->id, 'jumlah' => 20, 'harga_satuan' => 50000]],
            ])
            ->assertRedirect();

        // Pengajuan BOPD tidak boleh mengunci saldo awal BOS.
        $pengajuan = Pengajuan::create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $operator->id,
            'sumber_dana' => 'BOPD',
            'status' => 'submitted',
        ]);
        $pengajuan->details()->create([
            'barang_id' => $barangBopd->id,
            'jumlah_diminta' => 5,
            'jumlah_direservasi' => 5,
        ]);

        $detail = PenerimaanDetail::sumberDana('BOS')->where('barang_id', $barangBos->id)->firstOrFail();

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, PenerimaanDetail::sumberDana('BOS')->count());
    }

    public function test_hapus_detail_bukan_saldo_awal_ditolak(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $barang = $this->barang('BOS', 'Kertas HVS');

        $penerimaan = Penerimaan::create([
            'sekolah_id' => $sekolah->id,
            'sumber_dana' => 'BOS',
            'is_saldo_awal' => false,
        ]);
        $detail = PenerimaanDetail::create([
            'bhp_penerimaan_id' => $penerimaan->id,
            'barang_id' => $barang->id,
            'jumlah' => 5,
            'harga_satuan' => 1000,
            'nilai_total' => 5000,
        ]);

        $this->actingAs($operator)
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
            ->assertNotFound();
    }

    public function test_operator_sekolah_lain_tidak_bisa_menghapus_saldo_awal(): void
    {
        $sekolahA = $this->sekolah('SMKN A2');
        $sekolahB = $this->sekolah('SMKN B2');
        $operatorA = $this->operator($sekolahA, 'op.a2');
        $barang = $this->barang();

        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 4, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $detail = PenerimaanDetail::firstOrFail();

        $this->actingAs($this->operator($sekolahB, 'op.b2'))
            ->delete(route('distribusi-bhp.bos.saldo-awal.destroy', $detail))
            ->assertForbidden();

        $this->assertSame(1, PenerimaanDetail::count());
    }

    public function test_periode_menentukan_baris_yang_tampil(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang2025 = $this->barang('BOS', 'Kertas 2025');
        $barang2026 = $this->barang('BOS', 'Kertas 2026');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2025,
                'details' => [['barang_id' => $barang2025->id, 'jumlah' => 1, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang2026->id, 'jumlah' => 1, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $this->actingAs($operator)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2025]))
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('availablePeriods', fn ($periods) => collect($periods)->contains(2026))
            );
    }

    public function test_template_dapat_diunduh_berisi_kode_barang_sumber_dana(): void
    {
        $barang = $this->barang('BOS', 'Pulpen Biru');

        $response = $this->actingAs($this->operator($this->sekolah()))
            ->get(route('distribusi-bhp.bos.saldo-awal.template'));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        // Header + contoh barang BOS.
        $this->assertTrue($barang->exists);
    }

    public function test_import_saldo_awal_berhasil_semua_atau_batal(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $kertas = $this->barang('BOS', 'Kertas HVS');
        $pulpen = $this->barang('BOS', 'Pulpen Biru');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$kertas->kode_barang, 20, 50000, 'Sinar Dunia', ''],
            [$pulpen->kode_barang, 100, 3000, '', 'stok awal'],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), [
                'file' => $file,
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
            ])
            ->assertRedirect(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]));

        $this->assertSame(2, PenerimaanDetail::count());
        $this->assertSame(1, Penerimaan::count());
    }

    public function test_import_batal_semua_saat_ada_baris_invalid(): void
    {
        $sekolah = $this->sekolah();
        $operator = $this->operator($sekolah);
        $kertas = $this->barang('BOS', 'Kertas HVS');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$kertas->kode_barang, 20, 50000, '', ''],
            ['KODE-TIDAK-ADA', 5, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), [
                'file' => $file,
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
        $this->assertSame(0, PenerimaanDetail::count());
    }

    public function test_import_menolak_barang_beda_sumber_dana(): void
    {
        $operator = $this->operator($this->sekolah());
        $barangBopd = $this->barang('BOPD', 'Barang BOPD');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barangBopd->kode_barang, 5, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), [
                'file' => $file,
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_duplikat_dari_data_yang_sudah_ada(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang();

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, 10, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), [
                'file' => $file,
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, PenerimaanDetail::count());
    }

    /**
     * Bangun file .xlsx asli via PhpSpreadsheet agar lolos reader maatwebsite.
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, list<int>>  $teksCells  map rowIndex => [colIndex] yang
     *                                            dipaksa bertipe teks, supaya
     *                                            string seperti "1.500" tidak
     *                                            dikonversi jadi float oleh reader
     */
    private function xlsx(array $rows, array $teksCells = []): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $nilai) {
                if ($nilai === null) {
                    continue;
                }

                $koordinat = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
                $teks = in_array($colIndex, $teksCells[$rowIndex] ?? [], true);

                if ($teks) {
                    $sheet->setCellValueExplicit($koordinat, (string) $nilai, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($koordinat, $nilai);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'saldo-awal').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, 'saldo-awal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function import(string $sumberDana = 'BOS', int $periode = 2026): array
    {
        return ['sumber_dana' => $sumberDana, 'periode_tahun' => $periode];
    }

    public function test_import_menolak_berkas_tanpa_baris_data(): void
    {
        $operator = $this->operator($this->sekolah());

        // Hanya baris header, tanpa data.
        $file = $this->xlsx([['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan']]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_baris_kosong_trailing(): void
    {
        $operator = $this->operator($this->sekolah());

        // Header + baris kosong sepenuhnya (Excel trailing blank row).
        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [null, null, null, null, null],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menerima_harga_berformat_ribuan(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang('BOS', 'Kertas HVS');

        // Sel sengaja bertipe teks ("1.500" / "50.000") seperti ketikan
        // operator yang memakai pemisah ribuan.
        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, '1.500', '50.000', '', ''],
        ], [1 => [1, 2]]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('success');

        $detail = PenerimaanDetail::firstOrFail();
        $this->assertSame(1500, $detail->jumlah);
        $this->assertSame(50000, $detail->harga_satuan);
        $this->assertSame(75_000_000, $detail->nilai_total);
    }

    public function test_import_menolak_jumlah_desimal(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang('BOS', 'Kertas HVS');

        // Desimal nyata (sel numerik 1.5) harus ditolak — jumlah selalu unit.
        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, 1.5, 50000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_harga_desimal_dan_teks(): void
    {
        $operator = $this->operator($this->sekolah());
        $kertas = $this->barang('BOS', 'Kertas HVS');
        $pulpen = $this->barang('BOS', 'Pulpen Biru');

        // Harga desimal dan harga berisi teks — keduanya bukan angka rupiah.
        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$kertas->kode_barang, 10, 10000.5, '', ''],
            [$pulpen->kode_barang, 10, 'seribu', '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_jumlah_nol_atau_negatif(): void
    {
        $operator = $this->operator($this->sekolah());
        $kertas = $this->barang('BOS', 'Kertas HVS');
        $pulpen = $this->barang('BOS', 'Pulpen Biru');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$kertas->kode_barang, 0, 1000, '', ''],
            [$pulpen->kode_barang, -3, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_barang_duplikat_dalam_satu_berkas(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang('BOS', 'Kertas HVS');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, 10, 1000, '', ''],
            [$barang->kode_barang, 20, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_kode_barang_kosong(): void
    {
        $operator = $this->operator($this->sekolah());

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            ['', 10, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_lebih_dari_2000_baris(): void
    {
        $operator = $this->operator($this->sekolah());
        $barang = $this->barang('BOS', 'Kertas HVS');

        $rows = [['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan']];
        for ($i = 0; $i < 2001; $i++) {
            $rows[] = [$barang->kode_barang, 1, 1000, '', ''];
        }

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $this->xlsx($rows)] + $this->import())
            ->assertSessionHas('error');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_file_bukan_excel(): void
    {
        $operator = $this->operator($this->sekolah());

        $file = UploadedFile::fake()->create('data.pdf', 10, 'application/pdf');

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_menolak_periode_di_luar_rentang(): void
    {
        $operator = $this->operator($this->sekolah());

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            ['X', 1, 1000, '', ''],
        ]);

        $this->actingAs($operator)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file, 'sumber_dana' => 'BOS', 'periode_tahun' => 1900])
            ->assertSessionHasErrors('periode_tahun');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_bendahara_tanpa_permission_tidak_bisa_import(): void
    {
        $sekolah = $this->sekolah();
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.saldo.import',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');
        $barang = $this->barang('BOS', 'Kertas HVS');

        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, 10, 1000, '', ''],
        ]);

        $this->actingAs($bendahara)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertForbidden();

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_import_tidak_membocorkan_data_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN Import A');
        $sekolahB = $this->sekolah('SMKN Import B');
        $operatorB = $this->operator($sekolahB, 'op.import.b');
        $barang = $this->barang('BOS', 'Kertas HVS');

        // Sekolah A sudah punya saldo awal periode 2026 untuk barang yang sama.
        $operatorA = $this->operator($sekolahA, 'op.import.a');
        $this->actingAs($operatorA)
            ->post(route('distribusi-bhp.bos.saldo-awal.store'), [
                'sumber_dana' => 'BOS',
                'periode_tahun' => 2026,
                'details' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertRedirect();

        // Sekolah B tetap boleh import periode yang sama.
        $file = $this->xlsx([
            ['Kode Barang*', 'Jumlah*', 'Harga Satuan*', 'Merek/Tipe', 'Keterangan'],
            [$barang->kode_barang, 7, 1000, '', ''],
        ]);

        $this->actingAs($operatorB)
            ->post(route('distribusi-bhp.bos.saldo-awal.import'), ['file' => $file] + $this->import())
            ->assertSessionHas('success');

        $this->assertSame(2, Penerimaan::count());

        // Halaman sekolah B hanya menampilkan saldo awalnya (7 unit, bukan 5).
        $this->actingAs($operatorB)
            ->get(route('distribusi-bhp.bos.saldo-awal.index', ['periode' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('stats.total_unit', 7)
            );
    }
}
