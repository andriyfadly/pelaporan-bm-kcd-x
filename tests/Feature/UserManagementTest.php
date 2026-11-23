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
                'password' => 'Secret123!',
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
                'password' => 'Newpassword123!',
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

        // Guards: super_admin cannot be updated or deleted
        $super = User::create([
            'name' => 'Developer',
            'username' => 'dev_guard',
            'password' => bcrypt('password'),
        ]);
        $super->assignRole('super_admin');

        $this->actingAs($admin)
            ->put(route('admin.user.update', $super), ['username' => 'dev_guard'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.user.destroy', $super))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $super->id]);

        // Destroy
        $this->actingAs($admin)
            ->delete(route('admin.user.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_update_memetakan_role_admin_kcd_dan_bendahara(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD', 'username' => 'admin_map', 'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');

        $target = User::create([
            'name' => 'Target', 'username' => 'target_map', 'password' => bcrypt('password'),
        ]);
        $target->assignRole('operator_sekolah');

        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN Role Mapping',
            'kota_kab' => 'Kota Bandung',
        ]);

        // admin_kcd -> peran admin_kcd
        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_map', 'role' => 'admin_kcd', 'sekolah_id' => null,
            ])
            ->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole('admin_kcd'));

        // bendahara_sekolah -> peran bendahara_sekolah
        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'username' => 'target_map', 'role' => 'bendahara_sekolah', 'sekolah_id' => $sekolah->id,
            ])
            ->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole('bendahara_sekolah'));
    }

    public function test_update_tanpa_role_tidak_mengubah_peran(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD', 'username' => 'admin_norole', 'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');

        $target = User::create([
            'name' => 'Target', 'username' => 'target_norole', 'password' => bcrypt('password'),
        ]);
        $target->assignRole('operator_sekolah');

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), ['username' => 'target_norole'])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('operator_sekolah'));
    }

    public function test_store_menolak_role_admin_dengan_sekolah_dan_role_sekolah_tanpa_sekolah(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD', 'username' => 'admin_validation', 'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin_kcd');
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN Validasi',
            'kota_kab' => 'Kota Bandung',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.user.store'), [
                'name' => 'Admin Salah',
                'username' => 'admin_salah',
                'password' => 'Secret123!',
                'role' => 'admin_kcd',
                'sekolah_id' => $sekolah->id,
            ])
            ->assertSessionHasErrors('sekolah_id');

        $this->actingAs($admin)
            ->post(route('admin.user.store'), [
                'name' => 'Operator Salah',
                'username' => 'operator_salah',
                'password' => 'Secret123!',
                'role' => 'operator_sekolah',
            ])
            ->assertSessionHasErrors('sekolah_id');

        $this->assertDatabaseMissing('users', ['username' => 'admin_salah']);
        $this->assertDatabaseMissing('users', ['username' => 'operator_salah']);
    }
}
