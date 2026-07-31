<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->uuid('public_id')->nullable()->unique();
        });

        DB::table($table)->insert([['public_id' => null], ['public_id' => null]]);
    }
});

it('backfills public ids and remains idempotent', function () use ($tables): void {
    $this->artisan('app:backfill-public-ids', ['--chunk' => 1])
        ->assertSuccessful();

    foreach ($tables as $table) {
        $publicIds = DB::table($table)->orderBy('id')->pluck('public_id');

        expect($publicIds)->toHaveCount(2);

        foreach ($publicIds as $publicId) {
            expect(Str::isUuid($publicId))->toBeTrue()
                ->and($publicId[14])->toBe('7');
        }
    }

    $before = collect($tables)->mapWithKeys(
        fn (string $table): array => [$table => DB::table($table)->orderBy('id')->pluck('public_id')->all()],
    );

    $this->artisan('app:backfill-public-ids')->assertSuccessful();

    foreach ($tables as $table) {
        expect(DB::table($table)->orderBy('id')->pluck('public_id')->all())
            ->toBe($before[$table]);
    }
});

it('supports a dry run without writing public ids', function () use ($tables): void {
    $this->artisan('app:backfill-public-ids', ['--dry-run' => true])
        ->assertSuccessful();

    foreach ($tables as $table) {
        expect(DB::table($table)->whereNull('public_id')->count())->toBe(2);
    }
});
