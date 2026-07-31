<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('password');
        $table->enum('role', ['admin', 'user']);
        $table->unsignedInteger('id_sekolah')->nullable();
    });

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();

    DB::table('users')->insert([
        ['id' => 1, 'username' => 'admin', 'password' => 'unused', 'role' => 'admin', 'id_sekolah' => null],
        ['id' => 2, 'username' => 'operator', 'password' => 'unused', 'role' => 'user', 'id_sekolah' => 10],
    ]);
});

it('previews legacy role synchronization without writing', function (): void {
    $this->artisan('app:sync-legacy-roles', ['--dry-run' => true])
        ->expectsOutputToContain('2 user')
        ->assertSuccessful();

    expect(Role::query()->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('synchronizes legacy roles idempotently', function (): void {
    $this->artisan('app:sync-legacy-roles')->assertSuccessful();
    $this->artisan('app:sync-legacy-roles')->assertSuccessful();

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe(['admin', 'user'])
        ->and(User::query()->findOrFail(1)->hasRole('admin'))->toBeTrue()
        ->and(User::query()->findOrFail(2)->hasRole('user'))->toBeTrue()
        ->and(DB::table('model_has_roles')->count())->toBe(2);
});

it('preserves roles not owned by the legacy role column', function (): void {
    $auditor = Role::findOrCreate('auditor', 'web');
    $user = User::query()->findOrFail(2);
    $user->assignRole($auditor);

    $this->artisan('app:sync-legacy-roles')->assertSuccessful();

    expect($user->fresh()->hasAllRoles(['user', 'auditor']))->toBeTrue();
});

it('rejects an invalid chunk size before writing', function (): void {
    $this->artisan('app:sync-legacy-roles', ['--chunk' => 0])->assertFailed();

    expect(Role::query()->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('fails safely when a legacy role is unsupported', function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->string('role')->change();
    });
    DB::table('users')->where('id', 2)->update(['role' => 'super-admin']);

    $this->artisan('app:sync-legacy-roles')->assertFailed();

    expect(Role::query()->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});
