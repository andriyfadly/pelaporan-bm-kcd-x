<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    public function test_admin_can_manage_users_crud(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_test',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');

        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh',
            'kota_kab' => 'Kota Bandung',
        ]);

        // Index
        $this->actingAs($admin)
            ->get(route('admin.user.index'))
            ->assertOk();

        // Store
        $this->actingAs($admin)
            ->post(route('admin.user.store'), [
                'name' => 'Operator Baru',
                'username' => 'operator_baru',
                'password' => 'secret123',
                'role' => 'operator_sekolah',
                'sekolah_id' => $sekolah->id,
            ])
            ->assertRedirect();

        $user = User::where('username', 'operator_baru')->first();
        $this->assertNotNull($user);

        // Update
        $this->actingAs($admin)
            ->put(route('admin.user.update', $user), [
                'name' => 'Operator Baru Diedit',
                'username' => 'operator_baru',
                'password' => 'newpassword123',
                'role' => 'operator_sekolah',
                'sekolah_id' => $sekolah->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Operator Baru Diedit',
        ]);

        // Prevent deleting own account
        $this->actingAs($admin)
            ->delete(route('admin.user.destroy', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);

        // Destroy
        $this->actingAs($admin)
            ->delete(route('admin.user.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
