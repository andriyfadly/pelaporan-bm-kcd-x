<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kode klasifikasi surat disimpan pada profil sekolah dan dipakai penomoran
 * dokumen; operator sekolah lain tidak boleh melihat/mengubahnya.
 */
class InventarisPengaturanSuratTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);

        $this->sekolah = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Klasifikasi',
            'kota_kab' => 'Kota Bandung',
        ]));

        $this->operator = activity()->withoutLogging(fn () => User::create([
            'name' => 'Operator Klasifikasi',
            'username' => 'op_klasifikasi',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $this->sekolah->id,
            'password_changed_at' => now(),
        ]));
        $this->operator->assignRole('operator_sekolah');
    }

    public function test_profil_sekolah_menyimpan_kode_klasifikasi_surat(): void
    {
        $this->actingAs($this->operator)
            ->put(route('master-data.profil-sekolah.update'), [
                'alamat' => 'Jl. Klasifikasi 1',
                'kode_pos' => '45511',
                'kode_bast' => 'AR.03.05-CADISDIK.WIL-X',
                'kode_pengembalian' => 'AR.03.06-CADISDIK.WIL-X',
                'kode_fakta' => 'FKI.03.05',
            ])
            ->assertRedirect();

        $sekolah = $this->sekolah->fresh();

        $this->assertSame('AR.03.05-CADISDIK.WIL-X', $sekolah->kode_bast);
        $this->assertSame('AR.03.06-CADISDIK.WIL-X', $sekolah->kode_pengembalian);
        $this->assertSame('FKI.03.05', $sekolah->kode_fakta);
        $this->assertSame('45511', $sekolah->kode_pos);
    }

    public function test_halaman_profil_menampilkan_kode_klasifikasi(): void
    {
        $this->sekolah->update(['kode_bast' => 'AR.03.05']);

        $this->actingAs($this->operator)
            ->get(route('master-data.profil-sekolah.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sekolah.kode_bast', 'AR.03.05')
                ->has('sekolah.kode_pos'));
    }

    public function test_operator_sekolah_lain_tidak_bisa_mengubah_kode(): void
    {
        $lain = activity()->withoutLogging(fn () => Sekolah::create([
            'nama_sekolah' => 'SMA Lain Klasifikasi',
            'kota_kab' => 'Kota Bogor',
        ]));

        $this->actingAs($this->operator)
            ->put(route('master-data.profil-sekolah.update'), [
                'kode_bast' => 'HACK-1',
                'sekolah_id' => $lain->id,
            ])
            ->assertRedirect();

        // sekolah_id dari request diabaikan: tenant selalu dari sesi.
        $this->assertSame('HACK-1', $this->sekolah->fresh()->kode_bast);
        $this->assertNull($lain->fresh()->kode_bast);
    }
}
