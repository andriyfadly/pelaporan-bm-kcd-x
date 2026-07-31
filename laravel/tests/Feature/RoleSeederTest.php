<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $table) {
        Schema::dropIfExists($table);
    }

    $migration = require database_path('migrations/2026_07_30_162859_create_permission_tables.php');
    $migration->up();
});

it('seeds only BM roles without creating user accounts', function (): void {
    $this->seed(RoleSeeder::class);

    expect(Role::query()->pluck('name')->all())->toBe(['admin', 'user']);
});
