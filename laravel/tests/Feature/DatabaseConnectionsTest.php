<?php

it('defines three mysql database domains', function (): void {
    $environment = file_get_contents(base_path('.env.example'));

    expect($environment)
        ->toContain('DB_DATABASE=kcd_x_belanja_modal')
        ->toContain('MASTER_DB_USERNAME=kcd_x_master_reader')
        ->not->toContain('MASTER_DB_USERNAME=root')
        ->and(config('database.connections.master.database'))->toBe('kcd_x_master')
        ->and(config('database.connections.inventory.database'))->toBe('kcd_x_inventaris_sekolah')
        ->and(config('database.connections.master.driver'))->toBe('mysql')
        ->and(config('database.connections.inventory.driver'))->toBe('mysql');
});

it('does not let master credentials fall back to the transactional connection', function (): void {
    $configuration = file_get_contents(config_path('database.php'));

    expect($configuration)
        ->toContain("'username' => env('MASTER_DB_USERNAME')")
        ->toContain("'password' => env('MASTER_DB_PASSWORD')")
        ->not->toContain("env('MASTER_DB_USERNAME', env('DB_USERNAME'")
        ->not->toContain("env('MASTER_DB_PASSWORD', env('DB_PASSWORD'");
});
