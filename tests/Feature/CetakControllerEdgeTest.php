<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\PelaporanBm\Realisasi;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CetakControllerEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function adminTanpaSekolah(string $username = 'admin_cetak_edge'): User
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

    private function sekolah(string $nama = 'SMKN Cetak'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_cetak_edge'): User
    {
        $user = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]));
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_show_memakai_sekolah_stub_saat_database_sekolah_kosong(): void
    {
        // Admin tanpa sekolah & tak ada baris sekolah -> placeholder, bukan error.
        $admin = $this->adminTanpaSekolah();

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.cetak', ['bulan' => 5, 'tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/Cetak/Index')
                ->where('sekolah.nama_sekolah', 'Belum Ada Sekolah')
                ->where('sekolah.kota_kab', '-')
                ->has('items', 0)
            );
    }

    public function test_show_mengirim_daftar_sekolah_untuk_admin(): void
    {
        $admin = $this->adminTanpaSekolah('admin_cetak_list');
        $this->sekolah('SMKN Satu');
        $this->sekolah('SMKN Dua');

        $this->actingAs($admin)
            ->get(route('pelaporan-bm.cetak', ['bulan' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sekolahs', 2));
    }

    public function test_check_memfilter_berdasarkan_sekolah_operator(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $operator = $this->operator($sekolahA);

        // Dua realisasi bulan 5 tahun 2026: satu milik A, satu milik B.
        foreach ([$sekolahA, $sekolahB] as $s) {
            activity()->withoutLogging(fn () => Realisasi::create([
                'sekolah_id' => $s->id,
                'kodering_belanja' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'ba_tgl' => '2026-05-10',
                'no_spk' => 'SPK/'.substr($s->id, 0, 4),
                'kode_barang' => '1.3.2.01',
                'nama_barang' => 'Barang',
                'volume' => 1,
                'harga_satuan' => 1_000_000,
                'nilai_perolehan' => 1_000_000,
            ]));
        }

        // Operator sekolah A hanya boleh menghitung barisnya sendiri.
        $this->actingAs($operator)
            ->post(route('pelaporan-bm.cetak.check'), ['bulan' => 5, 'tahun' => 2026])
            ->assertOk()
            ->assertJson(['total_rows' => 1]);
    }

    public function test_check_menghitung_ba_tgl_null_sebagai_masuk(): void
    {
        $sekolah = $this->sekolah('SMKN NullTgl');
        $operator = $this->operator($sekolah, 'op_nulltgl');

        activity()->withoutLogging(fn () => Realisasi::create([
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'ba_tgl' => null,
            'no_spk' => 'SPK/NULL',
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 1_000_000,
            'nilai_perolehan' => 1_000_000,
        ]));

        $this->actingAs($operator)
            ->post(route('pelaporan-bm.cetak.check'), ['bulan' => 5, 'tahun' => 2026])
            ->assertOk()
            ->assertJson(['total_rows' => 1]);
    }
}
