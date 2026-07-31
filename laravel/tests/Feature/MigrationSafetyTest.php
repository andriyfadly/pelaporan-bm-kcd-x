<?php

it('does not recreate legacy-owned tables beyond explicit framework cache bootstrap', function (): void {
    $migrationSources = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $file): string => file_get_contents($file))
        ->implode("\n");

    foreach (['users', 'jobs', 'job_batches', 'failed_jobs', 'passkeys'] as $table) {
        expect($migrationSources)->not->toContain("Schema::create('{$table}'");
    }

    expect(database_path('migrations/2026_07_30_100000_create_cache_table.php'))->toBeFile();
});

it('does not add schema for disabled Fortify features', function (): void {
    $migrationPaths = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $file): string => basename($file));

    expect($migrationPaths)->not->toContain('2026_07_30_155456_add_two_factor_columns_to_users_table.php')
        ->not->toContain('2026_07_30_155457_create_passkeys_table.php');
});

it('never runs migrations from Composer lifecycle scripts', function (): void {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $scripts = collect($composer['scripts'])->flatten()->implode("\n");

    expect($scripts)->not->toContain('artisan migrate');
});
