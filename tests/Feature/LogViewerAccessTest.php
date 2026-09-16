<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    private function userDenganRole(string $role, string $username): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('Password123!'),
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_tamu_ditolak(): void
    {
        $this->get('/admin/log-error')->assertForbidden();
    }

    public function test_operator_ditolak(): void
    {
        $this->actingAs($this->userDenganRole('operator_sekolah', 'op_logviewer'))
            ->get('/admin/log-error')->assertForbidden();
    }

    public function test_super_admin_boleh_masuk(): void
    {
        $this->actingAs($this->userDenganRole('super_admin', 'super_logviewer'))
            ->get('/admin/log-error')->assertOk();
    }
}
