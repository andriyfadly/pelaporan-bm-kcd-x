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

class InputRealisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function sekolah(string $nama = 'SMKN 1 Realisasi'): Sekolah
    {
        return activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => $nama,
            'kota_kab' => 'Kota Bandung',
        ]));
    }

    private function operator(Sekolah $sekolah, string $username = 'op_real'): User
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

    private function acuan(Sekolah $sekolah, int $bulan, string $kodering, float $nominal): Acuan
    {
        return activity()->withoutLogging(fn () => Acuan::create([
            'sekolah_id' => $sekolah->id,
            'tanggal' => now()->toDateString(),
            'kodering' => $kodering,
            'uraian' => 'Belanja '.$kodering,
            'nominal' => $nominal,
            'bulan' => $bulan,
        ]));
    }

    private function spj(Sekolah $sekolah, int $bulan, string $kode, float $nilai, ?string $noSpk = 'SPK/01'): Spj
    {
        return activity()->withoutLogging(fn () => Spj::create([
            'sekolah_id' => $sekolah->id,
            'kategori' => 'Peralatan & Mesin',
            'sumber_perolehan' => 'BOS Reguler',
            'bulan_realisasi' => $bulan,
            'no_spk' => $noSpk,
            'kode_barang' => $kode,
            'nama_barang' => 'Barang '.$kode,
            'jenis_aset' => 'Peralatan dan Mesin',
            'satuan' => 'Unit',
            'volume' => 1,
            'harga_satuan' => $nilai,
            'nilai_perolehan' => $nilai,
        ]));
    }

    public function test_pilih_bulan_menormalkan_bulan_di_luar_rentang(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.pilih-bulan', ['bulan' => 99]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.pilih-bulan', ['bulan' => 0]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.pilih-bulan', ['bulan' => 6]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/InputRealisasi/PilihBulan')
                ->where('bulanAwal', 6));
    }

    public function test_index_menghitung_acuan_realisasi_dan_kekurangan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);

        $spj = $this->spj($sekolah, 5, '1.3.2.01', 400_000);
        activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 400_000,
            'nilai_perolehan' => 400_000,
        ]));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/InputRealisasi/Index')
                ->where('bulan', 5)
                ->where('totalAcuan', 1_000_000)
                ->where('totalRealisasi', 400_000)
                ->where('totalKekurangan', 600_000)
                ->where('isLocked', false)
                ->where('statusKirim', 'draft')
                ->has('daftarRekening', 1));
    }

    public function test_tambah_menolak_parameter_tidak_valid(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.tambah', ['kodering' => '', 'bulan_realisasi' => 5]))
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.tambah', ['kodering' => '5.2.02.01', 'bulan_realisasi' => 13]))
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index'))
            ->assertSessionHas('error');
    }

    public function test_tambah_menampilkan_pagu_sisa_dan_grup_spk(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $this->spj($sekolah, 5, '1.3.2.01', 250_000, 'SPK/A');
        $this->spj($sekolah, 5, '1.3.2.02', 150_000, 'SPK/B');

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.tambah', ['kodering' => '5.2.02.01', 'bulan_realisasi' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/InputRealisasi/Tambah')
                ->where('paguAcuan', 1_000_000)
                ->where('totalRealisasiSaatIni', 0)
                ->where('sisaAnggaran', 1_000_000)
                ->has('spkGroups', 2));
    }

    public function test_tambah_ditolak_saat_bulan_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.tambah', ['kodering' => '5.2.02.01', 'bulan_realisasi' => 5]))
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 5]))
            ->assertSessionHas('error');
    }

    public function test_simpan_menolak_tanpa_item(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'item_ids' => [],
            ])
            ->assertSessionHas('error');
    }

    public function test_simpan_menolak_bila_acuan_tidak_ada(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 100_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '9.9.9.99',
                'bulan_realisasi' => 5,
                'item_ids' => [$spj->id],
            ])
            ->assertSessionHas('error', 'Target acuan dengan kodering tersebut tidak ditemukan.');
    }

    public function test_simpan_menolak_item_dari_sekolah_lain(): void
    {
        $sekolahA = $this->sekolah('SMKN A');
        $sekolahB = $this->sekolah('SMKN B');
        $user = $this->operator($sekolahA);
        $this->acuan($sekolahA, 5, '5.2.02.01', 1_000_000);
        $spjLain = $this->spj($sekolahB, 5, '1.3.2.09', 100_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'item_ids' => [$spjLain->id],
            ])
            ->assertSessionHas('error', 'Data barang tidak valid.');

        $this->assertDatabaseCount('pelaporan_bm_realisasi', 0);
    }

    public function test_simpan_menolak_melebihi_sisa_anggaran(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 100_000);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 250_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'item_ids' => [$spj->id],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pelaporan_bm_realisasi', 0);
    }

    public function test_simpan_sukses_membuat_realisasi_dalam_batas_anggaran(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 400_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.simpan'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'item_ids' => [$spj->id],
            ])
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 5]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pelaporan_bm_realisasi', [
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'nilai_perolehan' => 400_000,
        ]);
    }

    public function test_edit_menolak_parameter_tidak_valid_dan_menandai_readonly_saat_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.edit', ['kodering' => '', 'bulan_realisasi' => 5]))
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index'))
            ->assertSessionHas('error');

        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('pelaporan-bm.input-realisasi.edit', ['kodering' => '5.2.02.01', 'bulan_realisasi' => 5]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PelaporanBm/InputRealisasi/Edit')
                ->where('isReadOnly', true));
    }

    public function test_update_menolak_parameter_tidak_valid(): void
    {
        $user = $this->operator($this->sekolah());

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.update'), [
                'kodering' => '',
                'bulan_realisasi' => 5,
            ])
            ->assertSessionHas('error', 'Parameter tidak valid.');
    }

    public function test_update_menghapus_realisasi_yang_diuncheck(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 400_000);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 400_000,
            'nilai_perolehan' => 400_000,
        ]));

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.update'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'uncheck_ids' => [$realisasi->id],
            ])
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 5]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('pelaporan_bm_realisasi', ['id' => $realisasi->id]);
    }

    public function test_update_tidak_boleh_menghapus_realisasi_bulan_lain(): void
    {
        // Worst case: uncheck_ids menunjuk baris bulan lain. Scope delete harus
        // dibatasi bulan_realisasi + kodering_belanja agar tidak menghapus lintas periode.
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $spjLain = $this->spj($sekolah, 6, '1.3.2.07', 300_000);

        $realisasiBulanLain = activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spjLain->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.99',
            'bulan_realisasi' => 6,
            'kode_barang' => '1.3.2.07',
            'nama_barang' => 'Barang Juni',
            'volume' => 1,
            'harga_satuan' => 300_000,
            'nilai_perolehan' => 300_000,
        ]));

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.update'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'uncheck_ids' => [$realisasiBulanLain->id],
            ]);

        $this->assertDatabaseHas('pelaporan_bm_realisasi', ['id' => $realisasiBulanLain->id]);
    }

    public function test_update_ditolak_saat_bulan_terkunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 400_000);

        $realisasi = activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 400_000,
            'nilai_perolehan' => 400_000,
        ]));

        activity()->withoutLogging(fn () => KunciLaporan::create([
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kunci' => true,
            'status_kirim' => 'draft',
        ]));

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.update'), [
                'kodering' => '5.2.02.01',
                'bulan_realisasi' => 5,
                'uncheck_ids' => [$realisasi->id],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pelaporan_bm_realisasi', ['id' => $realisasi->id]);
    }

    public function test_kirim_laporan_menolak_bila_belum_balance(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 1_000_000);
        $this->spj($sekolah, 5, '1.3.2.01', 100_000);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.kirim-laporan'), ['bulan_realisasi' => 5])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('pelaporan_bm_kunci_laporan', [
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kirim' => 'menunggu_approval',
        ]);
    }

    public function test_kirim_laporan_menolak_tanpa_target_acuan(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.kirim-laporan'), ['bulan_realisasi' => 5])
            ->assertSessionHas('error', 'Tidak ada target acuan untuk bulan ini.');
    }

    public function test_kirim_laporan_sukses_saat_balance_lalu_mengunci(): void
    {
        $sekolah = $this->sekolah();
        $user = $this->operator($sekolah);
        $this->acuan($sekolah, 5, '5.2.02.01', 500_000);
        $spj = $this->spj($sekolah, 5, '1.3.2.01', 500_000);

        // kirimLaporan menilai kelengkapan dari tabel realisasi (bukan SPJ mentah)
        activity()->withoutLogging(fn () => Realisasi::create([
            'spj_id' => $spj->id,
            'sekolah_id' => $sekolah->id,
            'kodering_belanja' => '5.2.02.01',
            'bulan_realisasi' => 5,
            'kode_barang' => '1.3.2.01',
            'nama_barang' => 'Barang',
            'volume' => 1,
            'harga_satuan' => 500_000,
            'nilai_perolehan' => 500_000,
        ]));

        $this->actingAs($user)
            ->post(route('pelaporan-bm.input-realisasi.kirim-laporan'), ['bulan_realisasi' => 5])
            ->assertRedirect(route('pelaporan-bm.input-realisasi.index', ['bulan_realisasi' => 5]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pelaporan_bm_kunci_laporan', [
            'sekolah_id' => $sekolah->id,
            'bulan' => 5,
            'status_kirim' => 'menunggu_approval',
            'status_kunci' => true,
        ]);
    }
}
