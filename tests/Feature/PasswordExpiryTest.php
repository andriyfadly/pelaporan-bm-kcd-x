<?php

namespace Tests\Feature;

use App\Models\Master\Sekolah;
use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'operator_sekolah']);
        Role::firstOrCreate(['name' => 'admin_kcd']);
        Role::firstOrCreate(['name' => 'super_admin']);
    }

    public function test_operator_with_null_password_changed_at_is_redirected_to_change_password(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Test',
            'kota_kab' => 'Kota Cirebon',
            'npsn' => '20222001',
        ]);

        $user = User::create([
            'name' => 'Operator Test',
            'username' => '20222001-admin',
            'password' => Hash::make('#SidiptaKCD10'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => null,
        ]);
        $user->assignRole('operator_sekolah');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('password.change'));
    }

    public function test_operator_with_expired_password_is_redirected_to_change_password(): void
    {
        $sekolah = Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Test',
            'kota_kab' => 'Kota Cirebon',
            'npsn' => '20222002',
        ]);

        $user = User::create([
            'name' => 'Operator Test',
            'username' => '20222002-admin',
            'password' => Hash::make('#SidiptaKCD10'),
            'sekolah_id' => $sekolah->id,
            'password_changed_at' => now()->subDays(95),
        ]);
        $user->assignRole('operator_sekolah');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('password.change'));
    }

    public function test_operator_cannot_update_with_simple_password(): void
    {
        $user = User::create([
            'name' => 'Operator Test',
            'username' => '20222003-admin',
            'password' => Hash::make('#SidiptaKCD10'),
            'password_changed_at' => null,
        ]);
        $user->assignRole('operator_sekolah');

        // Gagal jika < 8 karakter atau tanpa simbol / huruf besar
        $response = $this->actingAs($user)->post(route('password.update'), [
            'current_password' => '#SidiptaKCD10',
            'password' => 'simple',
            'password_confirmation' => 'simple',
        ]);

        $response->assertSessionHasErrors('password');
        $user->refresh();
        $this->assertNull($user->password_changed_at);
    }

    public function test_operator_can_update_expired_password_with_complex_password(): void
    {
        $user = User::create([
            'name' => 'Operator Test 2',
            'username' => '20222004-admin',
            'password' => Hash::make('#SidiptaKCD10'),
            'password_changed_at' => null,
        ]);
        $user->assignRole('operator_sekolah');

        $response = $this->actingAs($user)->post(route('password.update'), [
            'current_password' => '#SidiptaKCD10',
            'password' => 'PasswordBaru#123',
            'password_confirmation' => 'PasswordBaru#123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $user->refresh();
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('PasswordBaru#123', $user->password));

        $dashResponse = $this->actingAs($user)->get(route('dashboard'));
        $dashResponse->assertStatus(200);
    }

    public function test_admin_is_not_forced_to_change_password(): void
    {
        $admin = User::create([
            'name' => 'Admin KCD',
            'username' => 'admin_kcd',
            'password' => Hash::make('password'),
            'password_changed_at' => null,
        ]);
        $admin->assignRole('admin_kcd');

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_change_password_page_renders(): void
    {
        $user = User::create([
            'name' => 'Operator Form',
            'username' => 'form-op',
            'password' => Hash::make('#SidiptaKCD10'),
            'password_changed_at' => now(),
        ]);
        $user->assignRole('operator_sekolah');

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk();
    }

    public function test_user_seeder_creates_operator_accounts_with_npsn_admin_username(): void
    {
        Sekolah::create([
            'nama_sekolah' => 'SMKN 1 Contoh Seeder',
            'kota_kab' => 'Kota Cirebon',
            'npsn' => '20229999',
        ]);

        $this->seed(UserSeeder::class);

        $operator = User::where('username', '20229999-admin')->first();
        $this->assertNotNull($operator);
        $this->assertTrue($operator->hasRole('operator_sekolah'));
        $this->assertTrue(Hash::check('#SidiptaKCD10', $operator->password));
        $this->assertNull($operator->password_changed_at);
        $this->assertTrue($operator->isPasswordExpired());
    }

    public function test_user_seeder_creates_developer_and_admin_kcd_with_correct_passwords(): void
    {
        $this->seed(PeranDanHakAksesSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::where('username', 'admin_kcd')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('#SidiptaBeuKCD10', $admin->password));
        $this->assertFalse($admin->isPasswordExpired());

        $dev = User::where('username', 'developer')->first();
        $this->assertNotNull($dev);
        $this->assertTrue(Hash::check('#SidiptaBeuKCD10', $dev->password));
        $this->assertTrue($dev->hasRole('super_admin'));
        $this->assertFalse($dev->hasRole('admin_kcd'));
        $this->assertFalse($dev->isPasswordExpired());
    }
}
