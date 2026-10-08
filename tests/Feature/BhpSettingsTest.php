<?php

namespace Tests\Feature;

use App\Models\DistribusiBhp\Settings;
use App\Models\Master\Sekolah;
use App\Models\User;
use App\Services\DistribusiBhp\NomorDokumenService;
use App\Services\DistribusiBhp\SettingsService;
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
        app(SettingsService::class)->update(['format_nomor' => '{kode}-{sumber_dana}-{urut:4}/{tahun}']);

        $nomor = app(NomorDokumenService::class)->generate('bast', 'BOPD', 2026, 6);

        $this->assertSame('BAST-BOPD-0001/2026', $nomor);
    }

    public function test_operator_dapat_membuka_halaman_settings(): void
    {
        $this->actingAs($this->operator())
            ->get(route('distribusi-bhp.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('DistribusiBhp/Settings/Edit'));
    }

    public function test_operator_dapat_update_settings(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'format_nomor' => '{urut:2}/{kode}/{tahun}',
                'paper_size' => 'letter',
                'orientation' => 'landscape',
                'nama_penyetuju' => 'Budi',
                'jabatan_penyetuju' => 'Kepala Sekolah',
            ])
            ->assertRedirect();

        $settings = app(SettingsService::class)->get();
        $this->assertSame('{urut:2}/{kode}/{tahun}', $settings->format_nomor);
        $this->assertSame('letter', $settings->paper_size);
        $this->assertSame('landscape', $settings->orientation);
        // Kolom penyetuju sudah dihapus dari settings; penandatangan kini dari
        // master pejabat sekolah dengan snapshot di payload dokumen.
        $this->assertFalse(array_key_exists('nama_penyetuju', $settings->getAttributes()));
    }

    public function test_settings_format_tidak_valid_placeholder_tetap_aman(): void
    {
        // Validasi placeholder ada di controller (trust boundary HTTP);
        // service dipakai internal dan tetap aman terhadap format apa pun
        // — tidak error, tidak injection, hasil mengikuti format.
        app(SettingsService::class)->update(['format_nomor' => 'TANPA-PLACEHOLDER']);

        $nomor = app(NomorDokumenService::class)->generate('bast', 'BOS', 2026, 6);

        $this->assertSame('TANPA-PLACEHOLDER', $nomor);
    }

    public function test_preferensi_pdf_dipakai_dokumen_controller(): void
    {
        app(SettingsService::class)->update(['paper_size' => 'letter', 'orientation' => 'landscape']);

        $this->assertSame(['paper' => 'letter', 'orientation' => 'landscape'], app(SettingsService::class)->pdf());
    }

    public function test_get_berulang_tidak_membuat_lebih_dari_satu_baris_settings(): void
    {
        $service = app(SettingsService::class);

        $pertama = $service->get();
        for ($i = 0; $i < 5; $i++) {
            $service->get();
        }

        $this->assertSame(1, Settings::count());
        $this->assertSame($pertama->id, $service->get()->id);
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
            ->get(route('distribusi-bhp.settings.edit'))
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
                'format_nomor' => 'X/{urut:3}',
            ])
            ->assertForbidden();

        $this->assertSame(
            \App\Services\DistribusiBhp\SettingsService::DEFAULT_FORMAT_NOMOR,
            app(SettingsService::class)->get()->format_nomor,
        );
    }

    public function test_format_dengan_placeholder_tidak_aman_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'format_nomor' => '{urut:3}/{sumber_dana}/{placeholder_asing}',
            ])
            ->assertSessionHasErrors('format_nomor');

        $this->assertSame(
            \App\Services\DistribusiBhp\SettingsService::DEFAULT_FORMAT_NOMOR,
            app(SettingsService::class)->get()->format_nomor,
        );
    }

    public function test_format_dengan_semua_placeholder_sah_diterima(): void
    {
        $format = '{urut:4}-{kode}-{sumber_dana}-{bulan_romawi}-{bulan}-{tahun}';

        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'format_nomor' => $format,
            ])
            ->assertRedirect();

        $this->assertSame($format, app(SettingsService::class)->get()->format_nomor);
    }

    public function test_format_kosong_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'format_nomor' => '',
            ])
            ->assertSessionHasErrors('format_nomor');
    }

    public function test_format_tanpa_placeholder_ditolak(): void
    {
        // Format tanpa placeholder menghasilkan nomor dokumen duplikat — ditolak.
        $this->actingAs($this->operator())
            ->put(route('distribusi-bhp.settings.update'), [
                'format_nomor' => 'TANPA-PLACEHOLDER',
            ])
            ->assertSessionHasErrors('format_nomor');
    }
}
