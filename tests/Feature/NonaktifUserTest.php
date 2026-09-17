<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NonaktifUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function buatAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_nonaktif',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin_kcd');

        return $admin;
    }

    private function buatTarget(): User
    {
        $target = User::create([
            'name' => 'Operator Target',
            'username' => 'target_nonaktif',
            'password' => Hash::make('#SidiptaKCD10'),
        ]);
        $target->assignRole('operator_sekolah');

        return $target;
    }

    public function test_admin_bisa_menonaktifkan_user_dan_sesinya_terputus(): void
    {
        $admin = $this->buatAdmin();
        $target = $this->buatTarget();

        DB::table('sessions')->insert([
            'id' => 'sesi-target-1',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_nonaktif',
                'is_active' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => 0]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_user_nonaktif_ditolak_saat_login(): void
    {
        $admin = $this->buatAdmin();
        $target = $this->buatTarget();

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_nonaktif',
                'is_active' => 0,
            ])
            ->assertRedirect();

        // Reset guard: actingAs admin di atas tidak boleh memblokir attempt login target.
        $this->app->make('auth')->forgetGuards();

        $this->post('/login', [
            'username' => 'target_nonaktif',
            'password' => '#SidiptaKCD10',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_user_nonaktif_bisa_diaktifkan_kembali(): void
    {
        $admin = $this->buatAdmin();
        $target = $this->buatTarget();

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_nonaktif',
                'is_active' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_nonaktif',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => 1]);

        $this->app->make('auth')->forgetGuards();

        $this->post('/login', [
            'username' => 'target_nonaktif',
            'password' => '#SidiptaKCD10',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($target);
    }

    public function test_tidak_bisa_menonaktifkan_akun_sendiri(): void
    {
        $admin = $this->buatAdmin();

        $this->actingAs($admin)
            ->put(route('admin.user.update', $admin), [
                'username' => 'admin_nonaktif',
                'is_active' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => 1]);
    }

    public function test_halaman_index_menampilkan_status_user(): void
    {
        $admin = $this->buatAdmin();
        $target = $this->buatTarget();

        $this->actingAs($admin)
            ->get(route('admin.user.index'))
            ->assertOk()
            ->assertSee('target_nonaktif');

        $this->assertTrue((bool) $target->fresh()->is_active);
    }
}
