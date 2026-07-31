<?php

$legacyTables = [
    'data_barang_acuan',
    'kode_sekolah',
    'laporan_realisasi',
    'master_barang_sekolah',
    'realisasi_barang_sekolah',
    'realisasi_lock',
    'status_kirim_berkas',
    'users',
];

it('keeps a schema-only baseline for every legacy table', function () use ($legacyTables): void {
    $schema = file_get_contents(database_path('schema/mysql-schema.sql'));

    foreach ($legacyTables as $table) {
        expect($schema)->toContain("CREATE TABLE `{$table}`");
    }

    expect($schema)->not->toContain('INSERT INTO `users`')
        ->not->toContain('DEFINER=');
});

it('uses a dedicated testing database for database-mutating tests', function (): void {
    expect((string) config('database.connections.mysql.database'))->toEndWith('_testing');
});
