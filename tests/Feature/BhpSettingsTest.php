<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Settings;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\NomorDokumenService;
use App\Services\DistribusiBhp\SettingsService;
use App\Support\DistribusiBhp\SumberDana;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BhpSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function operator(): User
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Settings', 'kota_kab' => 'Kota Bandung']);
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op.settings',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_default_format_nomor_menghasilkan_nomor_lama(): void
    {
        $service = app(SettingsService::class);
        $nomor = app(NomorDokumenService::class)->generate('nota_permintaan', 'BOS', 2026, 6);

        $this->assertSame('001/BHP/NPB/BOS/VI/2026', $nomor);
        $this->assertNotNull($service->get());
    }

    public function test_format_nomor_bisa_diubah_melalui_settings(): void
    {
        app(SettingsService::class)->update(['format_nomor' => '{kode}-{sumber_dana}-{urut:4}/{tahun}'], 'BOPD');

        $nomor = app(NomorDokumenService::class)->generate('bast', 'BOPD', 2026, 6);

        $this->assertSame('BAST-BOPD-0001/2026', $nomor);
    }

    public function test_operator_dapat_membuka_halaman_settings(): void
    {
        $this->actingAs($this->operator())
            ->get(route('distribusi-bhp.settings.edit', ['sumber_dana' => 'BOS']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DistribusiBhp/Settings/Edit')
                ->where('sumberDana', 'BOS')
            );
    }

    public function test_operator_dapat_update_settings_bos(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '{urut:2}/{kode}/{tahun}',
            ])
            ->assertRedirect();

        $settings = app(SettingsService::class)->get('BOS');
        $this->assertSame('{urut:2}/{kode}/{tahun}', $settings->format_nomor);
    }

    public function test_settings_bos_dan_bopd_terpisah(): void
    {
        // Settings BOS dan BOPD harus independent.
        app(SettingsService::class)->update(['format_nomor' => 'BOS-{urut:3}'], 'BOS');
        app(SettingsService::class)->update(['format_nomor' => 'BOPD-{urut:4}'], 'BOPD');

        $bos = app(SettingsService::class)->get('BOS');
        $bopd = app(SettingsService::class)->get('BOPD');

        $this->assertSame('BOS-{urut:3}', $bos->format_nomor);
        $this->assertSame('BOPD-{urut:4}', $bopd->format_nomor);
    }

    public function test_settings_format_tidak_valid_placeholder_tetap_aman(): void
    {
        // Validasi placeholder ada di controller (trust boundary HTTP);
        // service dipakai internal dan tetap aman terhadap format apa pun
        // — tidak error, tidak injection, hasil mengikuti format.
        app(SettingsService::class)->update(['format_nomor' => 'TANPA-PLACEHOLDER'], 'BOS');

        $nomor = app(NomorDokumenService::class)->generate('bast', 'BOS', 2026, 6);

        $this->assertSame('TANPA-PLACEHOLDER', $nomor);
    }

    public function test_preferensi_pdf_dipakai_dokumen_controller(): void
    {
        $this->assertSame(['paper' => 'a4', 'orientation' => 'portrait'], app(SettingsService::class)->pdf());
    }

    public function test_get_berulang_tidak_membuat_lebih_dari_satu_baris_settings_per_dana(): void
    {
        $service = app(SettingsService::class);

        $pertama = $service->get('BOS');
        for ($i = 0; $i < 5; $i++) {
            $service->get('BOS');
        }

        // Hanya satu baris untuk BOS — BOPD belum dipanggil.
        $this->assertSame(1, SumberDana::dengan('BOS', fn () => Settings::count()));
        $this->assertSame($pertama->id, $service->get('BOS')->id);

        // BOPD baru dibuat saat pertama kali dipanggil.
        $bopd = $service->get('BOPD');
        $this->assertNotNull($bopd);

        // Total 1 baris per dana, di tabel terpisah.
        $this->assertSame(1, SumberDana::dengan('BOS', fn () => Settings::count()));
        $this->assertSame(1, SumberDana::dengan('BOPD', fn () => Settings::count()));
    }

    public function test_role_tanpa_permission_ditolak_melihat_settings(): void
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Bendahara', 'kota_kab' => 'Kota Bandung']);
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.settings',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->get(route('distribusi-bhp.settings.edit', ['sumber_dana' => 'BOS']))
            ->assertForbidden();
    }

    public function test_role_tanpa_permission_ditolak_update_settings(): void
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN Bendahara2', 'kota_kab' => 'Kota Bandung']);
        $bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara.settings2',
            'password' => bcrypt('password'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $bendahara->assignRole('bendahara_sekolah');

        $this->actingAs($bendahara)
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => 'X/{urut:3}',
            ])
            ->assertForbidden();

        $this->assertSame(
            Settings::DEFAULT_FORMAT,
            app(SettingsService::class)->get('BOS')->format_nomor,
        );
    }

    public function test_format_dengan_placeholder_tidak_aman_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '{urut:3}/{sumber_dana}/{placeholder_asing}',
            ])
            ->assertSessionHasErrors('format_nomor');

        $this->assertSame(
            Settings::DEFAULT_FORMAT,
            app(SettingsService::class)->get('BOS')->format_nomor,
        );
    }

    public function test_format_dengan_semua_placeholder_sah_diterima(): void
    {
        $format = '{urut:4}-{kode}-{sumber_dana}-{bulan_romawi}-{bulan}-{tahun}';

        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => $format,
            ])
            ->assertRedirect();

        $this->assertSame($format, app(SettingsService::class)->get('BOS')->format_nomor);
    }

    public function test_format_kosong_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '',
            ])
            ->assertSessionHasErrors('format_nomor');
    }

    public function test_format_tanpa_placeholder_ditolak(): void
    {
        // Format tanpa placeholder menghasilkan nomor dokumen duplikat — ditolak.
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => 'TANPA-PLACEHOLDER',
            ])
            ->assertSessionHasErrors('format_nomor');
    }

    public function test_sequence_bos_dan_bopd_berjalan_terpisah(): void
    {
        $service = app(NomorDokumenService::class);

        $bos1 = $service->generate('buku_pengeluaran', 'BOS', 2026, 6);
        $bos2 = $service->generate('buku_pengeluaran', 'BOS', 2026, 6);
        $bopd1 = $service->generate('buku_pengeluaran', 'BOPD', 2026, 6);

        $this->assertSame('001/BHP/BPB/BOS/VI/2026', $bos1);
        $this->assertSame('002/BHP/BPB/BOS/VI/2026', $bos2);
        // BOPD mulai dari 001 lagi — bukan melanjutkan counter BOS.
        $this->assertSame('001/BHP/BPB/BOPD/VI/2026', $bopd1);
    }

    public function test_sequence_terpisah_per_jenis_dokumen(): void
    {
        $service = app(NomorDokumenService::class);

        $this->assertSame('001/BHP/NPB/BOS/VI/2026', $service->generate('nota_permintaan', 'BOS', 2026, 6));
        $this->assertSame('001/BHP/BAST/BOS/VI/2026', $service->generate('bast', 'BOS', 2026, 6));
        $this->assertSame('002/BHP/NPB/BOS/VI/2026', $service->generate('nota_permintaan', 'BOS', 2026, 6));
    }

    public function test_sequence_terpisah_per_tahun_dan_bulan(): void
    {
        $service = app(NomorDokumenService::class);

        $this->assertSame('001/BHP/NPB/BOS/VI/2026', $service->generate('nota_permintaan', 'BOS', 2026, 6));
        $this->assertSame('001/BHP/NPB/BOS/VII/2026', $service->generate('nota_permintaan', 'BOS', 2026, 7));
        $this->assertSame('001/BHP/NPB/BOS/VI/2027', $service->generate('nota_permintaan', 'BOS', 2027, 6));
        $this->assertSame('002/BHP/NPB/BOS/VI/2026', $service->generate('nota_permintaan', 'BOS', 2026, 6));
    }

    public function test_generate_nomor_berurutan_tidak_pernah_duplikat(): void
    {
        $service = app(NomorDokumenService::class);

        $nomor = [];
        for ($i = 0; $i < 25; $i++) {
            $nomor[] = $service->generate('nota_permintaan', 'BOS', 2026, 6);
        }

        $this->assertCount(25, array_unique($nomor), 'Nomor dokumen tidak boleh duplikat.');
        $this->assertSame('025/BHP/NPB/BOS/VI/2026', end($nomor));
    }

    public function test_placeholder_urut_berbeda_mengikuti_padding_masing_masing(): void
    {
        app(SettingsService::class)->update(['format_nomor' => '{urut:1}-{urut:2}-{urut:3}-{urut:4}'], 'BOS');
        $service = app(NomorDokumenService::class);

        // Sequence pertama = 1.
        $this->assertSame('1-01-001-0001', $service->generate('bast', 'BOS', 2026, 6));
        // Lalu naik ke sequence 12 untuk mengecek lebar padding di atas 9.
        for ($i = 0; $i < 10; $i++) {
            $service->generate('bast', 'BOS', 2026, 6);
        }
        $this->assertSame('12-12-012-0012', $service->generate('bast', 'BOS', 2026, 6));
    }

    public function test_format_per_jenis_dokumen_override_format_umum(): void
    {
        // Set format umum + override spesifik untuk surat_permintaan.
        app(SettingsService::class)->update([
            'format_nomor' => '{kode}/{urut:3}/{tahun}',
            'format_nomor_surat_permintaan' => 'SPB-{sumber_dana}-{urut:4}/{tahun}',
        ], 'BOS');

        $service = app(NomorDokumenService::class);

        // surat_permintaan pakai override.
        $this->assertSame('SPB-BOS-0001/2026', $service->generate('surat_permintaan', 'BOS', 2026, 6));

        // jenis lain (nota_permintaan) fallback ke format umum.
        $this->assertSame('NPB/001/2026', $service->generate('nota_permintaan', 'BOS', 2026, 6));
    }

    public function test_format_per_jenis_bisa_diisi_via_api(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '{urut:3}/{kode}/{tahun}',
                'format_nomor_bast' => 'BAST-{bulan_romawi}-{urut:4}/{tahun}',
                'format_nomor_bap' => 'BAP/BOS/{tahun}/{urut:2}',
            ])
            ->assertRedirect();

        $settings = app(SettingsService::class)->get('BOS');
        $this->assertSame('BAST-{bulan_romawi}-{urut:4}/{tahun}', $settings->format_nomor_bast);
        $this->assertSame('BAP/BOS/{tahun}/{urut:2}', $settings->format_nomor_bap);

        // Default umum juga tersimpan.
        $this->assertSame('{urut:3}/{kode}/{tahun}', $settings->format_nomor);
    }

    public function test_format_per_jenis_validasi_placeholder_asing_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '{urut:3}/{kode}',
                'format_nomor_bast' => '{unknown}/{urut:3}',
            ])
            ->assertSessionHasErrors('format_nomor_bast');
    }

    public function test_format_per_jenis_tanpa_placeholder_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'sumber_dana' => 'BOS',
                'format_nomor' => '{urut:3}/{kode}',
                'format_nomor_surat_permintaan' => 'TANPA-PLACEHOLDER',
            ])
            ->assertSessionHasErrors('format_nomor_surat_permintaan');
    }
}
