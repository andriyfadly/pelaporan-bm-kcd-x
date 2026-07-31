<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$tables = [
    'kode_sekolah',
    'users',
    'data_barang_acuan',
    'laporan_realisasi',
    'master_barang_sekolah',
    'realisasi_barang_sekolah',
];

beforeEach(function () use ($tables): void {
    foreach ($tables as $table) {
        Schema::dropIfExists($table);
        Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
    }
});

it('adds nullable public ids without changing internal ids', function () use ($tables): void {
    $migration = require database_path('migrations/2026_07_30_000001_add_public_ids_to_legacy_tables.php');
    $migration->up();

    foreach ($tables as $table) {
        expect(Schema::hasColumn($table, 'id'))->toBeTrue()
            ->and(Schema::hasColumn($table, 'public_id'))->toBeTrue();
    }
});
