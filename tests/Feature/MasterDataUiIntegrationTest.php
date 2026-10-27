<?php

namespace Tests\Feature;

use App\Models\Master\Jabatan;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\Master\PenugasanPegawaiService;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrak props antara controller dan halaman Inertia. Kalau salah satu
 * controller berhenti mengirim prop yang dibaca TSX, halaman akan blank tanpa
 * error PHP — jadi kontraknya dikunci di sini.
 */
class MasterDataUiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Integrasi',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = User::create([
            'name' => 'Operator',
            'username' => 'op.integrasi',
            'password' => bcrypt('password'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]);
        $this->operator->assignRole('operator_sekolah');
    }

    public function test_halaman_ruang_mengirim_prop_yang_dibaca_ui(): void
    {
        activity()->withoutLogging(fn () => Ruang::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_ruang' => 'Ruang Guru',
            'kode' => 'RG',
        ]));

        $this->actingAs($this->operator)
            ->get(route('master-data.ruang.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Ruang/Index')
                ->has('ruang', 1)
                ->has('ruang.0', fn ($item) => $item
                    ->hasAll(['id', 'nama_ruang', 'kode', 'unit_aktif_count'])
                    ->etc()));
    }

    public function test_halaman_pegawai_mengirim_prop_yang_dibaca_ui(): void
    {
        activity()->withoutLogging(fn () => Pegawai::create([
            'sekolah_id' => $this->sekolah->id,
            'nama_lengkap' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'pangkat_golongan' => 'III/a',
        ]));

        $this->actingAs($this->operator)
            ->get(route('master-data.pegawai.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Pegawai/Index')
                ->has('pegawai.0', fn ($item) => $item
                    ->hasAll(['id', 'nama_lengkap', 'nip', 'pangkat_golongan', 'unit_aktif_count'])
                    ->etc()));
    }

    public function test_halaman_jabatan_mengirim_prop_wajib_dan_kategori_yang_dibaca_ui(): void
    {
        $this->actingAs($this->operator)
            ->get(route('master-data.jabatan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Jabatan/Index')
                ->has('kategoriList')
                ->has('jabatan', 3)
                ->has('jabatan.0', fn ($item) => $item
                    ->hasAll(['id', 'kode', 'nama', 'kategori', 'aktif', 'wajib', 'penugasan_aktif_count'])
                    ->where('wajib', true)
                    ->etc()));

        // Bendera wajib harus benar-benar membedakan jabatan penandatangan
        // dari jabatan biasa, karena UI memakainya untuk menyembunyikan
        // tombol hapus dan mengunci input.
        activity()->withoutLogging(fn () => Jabatan::create([
            'sekolah_id' => $this->sekolah->id,
            'kode' => 'bendahara',
            'nama' => 'Bendahara',
        ]));

        $this->actingAs($this->operator)
            ->get(route('master-data.jabatan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('jabatan', 4)
                ->where('jabatan', fn ($items) => collect($items)
                    ->firstWhere('kode', 'bendahara')['wajib'] === false
                    && collect($items)->firstWhere('kode', 'kepala_sekolah')['wajib'] === true));
    }

    public function test_halaman_penugasan_selalu_punya_pilihan_jabatan_walau_belum_ada_penugasan(): void
    {
        // Sekolah baru: belum pernah membuka halaman Jabatan, belum ada
        // penugasan. Dropdown jabatan tetap harus terisi.
        $this->assertSame(0, Jabatan::count());

        $this->actingAs($this->operator)
            ->get(route('master-data.penugasan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Penugasan/Index')
                ->has('penugasan', 0)
                ->has('jabatanList', 3)
                ->has('jabatanList.0', fn ($item) => $item->hasAll(['id', 'kode', 'nama'])->etc())
                ->has('jenisList', 3)
                ->has('labelJenis'));
    }

    public function test_halaman_penugasan_mengirim_relasi_pegawai_dan_jabatan(): void
    {
        $this->actingAs($this->operator)->post(route('master-data.penugasan.store'), [
            'nama' => 'Budi Santoso',
            'nip' => '198001012005011001',
            'jabatan' => 'kepala_sekolah',
            'jenis_penugasan' => 'definitif',
            'mulai_menjabat' => '2026-01-01',
        ])->assertSessionHas('success');

        $this->actingAs($this->operator)
            ->get(route('master-data.penugasan.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('penugasan', 1)
                ->has('penugasan.0', fn ($item) => $item
                    ->hasAll(['id', 'pegawai_id', 'jabatan_id', 'jenis_penugasan', 'mulai_menjabat', 'selesai_menjabat', 'pegawai', 'jabatan'])
                    ->has('pegawai', fn ($pegawai) => $pegawai->hasAll(['id', 'nama_lengkap', 'nip', 'pangkat_golongan'])->etc())
                    ->has('jabatan', fn ($jabatan) => $jabatan->hasAll(['id', 'kode', 'nama'])->etc())
                    ->etc()));
    }

    public function test_semua_tombol_master_data_mengarah_ke_route_yang_ada(): void
    {
        $this->actingAs($this->operator)->get(route('master-data.penugasan.index'));

        $penugasan = app(PenugasanPegawaiService::class)->simpan(
            $this->sekolah->id,
            $this->operator->id,
            ['nama' => 'Budi', 'jabatan' => 'kepala_sekolah', 'mulai_menjabat' => '2026-01-01'],
        );

        $harusAda = [
            'master-data.ruang.index', 'master-data.ruang.store', 'master-data.ruang.update', 'master-data.ruang.destroy',
            'master-data.pegawai.index', 'master-data.pegawai.store', 'master-data.pegawai.update', 'master-data.pegawai.destroy',
            'master-data.jabatan.index', 'master-data.jabatan.store', 'master-data.jabatan.update', 'master-data.jabatan.destroy',
            'master-data.penugasan.index', 'master-data.penugasan.store', 'master-data.penugasan.update',
            'master-data.penugasan.akhiri', 'master-data.penugasan.mutasi',
        ];

        foreach ($harusAda as $nama) {
            $this->assertTrue(
                app('router')->has($nama),
                "Route {$nama} dipakai UI tetapi tidak terdaftar.",
            );
        }

        // Endpoint yang butuh parameter harus menghasilkan URL nyata, bukan
        // placeholder kosong.
        $url = route('master-data.penugasan.akhiri', $penugasan);
        $this->assertStringContainsString($penugasan->id, $url);
    }
}
