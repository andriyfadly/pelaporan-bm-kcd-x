<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Signature('app:sync-legacy-roles {--dry-run : Preview changes without writing} {--chunk=200 : Users per chunk}')]
#[Description('Synchronize users.role into Spatie roles without removing the legacy column')]
class SyncLegacyRoles extends Command
{
    public function handle(): int
    {
        $allowed = ['admin', 'user'];
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($chunk === false) {
            $this->error('Chunk must be an integer greater than zero.');

            return self::FAILURE;
        }

        $legacyRoles = User::query()->distinct()->pluck('role')->filter()->values();
        $unsupported = $legacyRoles->diff($allowed);

        if ($unsupported->isNotEmpty()) {
            $this->error('Unsupported legacy roles: '.$unsupported->implode(', '));

            return self::FAILURE;
        }

        $users = User::query()->whereIn('role', $allowed)->count();
        $this->info("{$users} user akan disinkronkan.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($allowed, $chunk): void {
            foreach ($allowed as $name) {
                Role::findOrCreate($name, 'web');
            }

            User::query()
                ->whereIn('role', $allowed)
                ->orderBy('id')
                ->chunkById($chunk, function ($users): void {
                    foreach ($users as $user) {
                        $user->assignRole($user->role);
                    }
                });
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
