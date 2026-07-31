<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'user'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }
}
