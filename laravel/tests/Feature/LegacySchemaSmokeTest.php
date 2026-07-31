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

it('keeps a legacy table catalog without an executable schema dump', function () use ($legacyTables): void {
    expect($legacyTables)->toHaveCount(8)
        ->and(database_path('schema/mysql-schema.sql'))->not->toBeFile();
});

it('uses a dedicated testing database for database-mutating tests', function (): void {
    expect((string) config('database.connections.mysql.database'))->toEndWith('_testing');
});
