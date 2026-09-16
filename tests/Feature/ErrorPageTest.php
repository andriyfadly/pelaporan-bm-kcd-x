<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function operator(): User
    {
        $sekolah = Sekolah::create(['nama_sekolah' => 'SMKN 1 Err', 'kota_kab' => 'Kota Bandung']);
        $user = User::create([
            'name' => 'Operator',
            'username' => 'op_errorpage',
            'password' => bcrypt('Password123!'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        return $user;
    }

    public function test_404_blade_kustom_tanpa_x_inertia(): void
    {
        $this->actingAs($this->operator())
            ->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan');
    }

    public function test_403_blade_kustom_dan_tanpa_pesan_mentah(): void
    {
        $this->actingAs($this->operator())
            ->get(route('admin.user.index'))
            ->assertForbidden()
            ->assertSee('Akses Ditolak');
    }

    public function test_404_inertia_render_komponen_error(): void
    {
        $response = $this->actingAs($this->operator())->get(
            '/halaman-yang-tidak-ada',
            ['X-Inertia' => 'true']
        );

        $response->assertNotFound();
        $data = $response->json();
        $this->assertSame('Error', $data['component'] ?? null);
        $this->assertSame(404, $data['props']['status'] ?? null);
    }
}
