<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Acuan;
use App\Models\PelaporanBm\KunciLaporan;
use App\Models\PelaporanBm\Realisasi;
use App\Models\PelaporanBm\Spj;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapanEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function admin(string $username = 'admin_rekap_edge'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Admin KCD',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]));
        $user->assignRole('admin_kcd');

        return $user;
    }

    private function sekolah(string $nama): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function acuan(Sekolah $sekolah, float $nominal, string $kodering = '5.2.02.05', string $uraian = 'Komputer'): Acuan
    {
        return activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'kodering' => $kodering,
            'uraian' => $uraian,
            'nominal' => $nominal,
        ]));
    }

    private function realisasi(Sekolah $sekolah, Acuan $acuan, float $nilai): Realisasi
    {
        return activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'acuan_id' => $acuan->id,
            'kodering_belanja' => $acuan->kodering ?: 'TANPA KODERING',
            'bulan_realisasi' => 5,
            'no_spk' => 'SPK-01',
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => $nilai,
            'nilai_perolehan' => $nilai,
        ]));
    }

    public function test_status_kirim_label_menunggu_dan_disetujui(): void
    {
        $admin = $this->admin();
        $menunggu = $this->sekolah('SMKN Menunggu');
        $disetujui = $this->sekolah('SMKN Disetujui');

        $this->acuan($menunggu, 10_000_000);
        $this->acuan($disetujui, 10_000_000);

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $menunggu->id, 'bulan' => 5,
            'status_kirim' => 'menunggu_approval', 'status_kunci' => true,
        ]));
        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $disetujui->id, 'bulan' => 5,
            'status_kirim' => 'disetujui', 'status_kunci' => true,
        ]));

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $items = collect($page->toArray()['props']['items'])->keyBy('nama_sekolah');
                $this->assertSame('Menunggu Approval', $items['SMKN Menunggu']['status_kirim']);
                $this->assertSame('Disetujui', $items['SMKN Disetujui']['status_kirim']);
                // Disetujui -> TUNTAS, menunggu -> BELUM.
                $this->assertSame('TUNTAS', $items['SMKN Disetujui']['status']);
                $this->assertSame('BELUM', $items['SMKN Menunggu']['status']);
            });
    }

    public function test_realisasi_dikelompokkan_per_acuan_dari_acuan_id(): void
    {
        $admin = $this->admin('admin_rekap_acuan');
        $sekolah = $this->sekolah('SMKN AcuanId');
        $acuan = $this->acuan($sekolah, 20_000_000);

        // Realisasi dialokasikan ke acuan (paritas legacy id_uraian).
        $this->realisasi($sekolah, $acuan, 15_000_000);

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $item = collect($page->toArray()['props']['items'])->firstWhere('nama_sekolah', 'SMKN AcuanId');
                $this->assertEquals(15_000_000, $item['total_realisasi']);
                $rek = collect($item['rekening_acuan'])->firstWhere('kodering', '5.2.02.05');
                $this->assertEquals(15_000_000, $rek['realisasi']);
                $this->assertEquals(5_000_000, $rek['kekurangan']);
            });
    }

    public function test_item_tuntas_diurutkan_dulu_lalu_alfabetis(): void
    {
        $admin = $this->admin('admin_rekap_sort');
        $bTuntas = $this->sekolah('SMKN B Tuntas');
        $aBelum = $this->sekolah('SMKN A Belum');
        $cBelum = $this->sekolah('SMKN C Belum');

        // B tuntas via status disetujui.
        $this->acuan($bTuntas, 1_000_000);
        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $bTuntas->id, 'bulan' => 5,
            'status_kirim' => 'disetujui', 'status_kunci' => true,
        ]));

        // A & C belum (tak tuntas) -> urut alfabetis: A sebelum C.
        $this->acuan($aBelum, 1_000_000);
        $this->acuan($cBelum, 1_000_000);

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $nama = collect($page->toArray()['props']['items'])
                    ->filter(fn ($i) => str_contains($i['nama_sekolah'], 'Tuntas') || str_contains($i['nama_sekolah'], 'Belum'))
                    ->pluck('nama_sekolah')->all();
                $this->assertSame(
                    ['SMKN B Tuntas', 'SMKN A Belum', 'SMKN C Belum'],
                    $nama
                );
            });
    }

    public function test_parity_legacy_baris_dari_acuan_dan_realisasi_dari_alokasi(): void
    {
        $admin = $this->admin('admin_rekap_parity');

        // Sekolah TANPA acuan pada bulan terpilih tidak boleh muncul (legacy).
        $tanpaAcuan = $this->sekolah('SMKN TanpaAcuan');
        activity()->withoutLogging(fn () => Spj::create([
            'sekolah_id' => $tanpaAcuan->id,
            'no_spk' => 'SPK-TANPA-ACUAN',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Lepas',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 5_000_000,
            'nilai_perolehan' => 5_000_000,
        ]));

        // Sekolah dengan acuan: SPJ yang belum dialokasikan TIDAK dihitung realisasi.
        $sekolah = $this->sekolah('SMKN DenganAcuan');
        $this->acuan($sekolah, 10_000_000);
        activity()->withoutLogging(fn () => Spj::create([
            'sekolah_id' => $sekolah->id,
            'no_spk' => 'SPK-BELUM-ALOKASI',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.05',
            'nama_barang' => 'Laptop Belum Alokasi',
            'jenis_aset' => 'Peralatan dan Mesin',
            'volume' => 1,
            'harga_satuan' => 7_000_000,
            'nilai_perolehan' => 7_000_000,
        ]));

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $items = collect($page->toArray()['props']['items']);
                $this->assertCount(1, $items);
                $item = $items->first();
                $this->assertSame('SMKN DenganAcuan', $item['nama_sekolah']);
                $this->assertEquals(0, $item['total_realisasi']);
                $this->assertSame('BELUM', $item['status']);
                $this->assertSame(0, $item['progres']['match']);
                $this->assertSame(1, $item['progres']['total']);
                $this->assertSame([], $item['log_fisik']);
            });
    }

    public function test_kodering_acuan_tanpa_kodering_dan_agregasi_uraian(): void
    {
        $admin = $this->admin('admin_rekap_kosong');
        $sekolah = $this->sekolah('SMKN TanpaKode');

        // Dua acuan dengan kodering kosong & uraian berbeda -> satu grup TANPA KODERING.
        $this->acuan($sekolah, 1_000_000, '', 'Uraian A');
        $this->acuan($sekolah, 2_000_000, '', 'Uraian B');

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.rekapan.index', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $item = collect($page->toArray()['props']['items'])->firstWhere('nama_sekolah', 'SMKN TanpaKode');
                $rek = collect($item['rekening_acuan']);
                $tanpa = $rek->firstWhere('kodering', 'TANPA KODERING');
                $this->assertNotNull($tanpa);
                $this->assertEquals(3_000_000, $tanpa['acuan']);
                // kodering "TANPA KODERING" tidak dihitung ke progres match.
                $this->assertSame(0, $item['progres']['total']);
            });
    }
}
