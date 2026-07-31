<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('master_school_projections');
    Schema::create('master_school_projections', function (Blueprint $table): void {
        $table->id();
        $table->uuid('public_id')->unique();
        $table->string('npsn', 20)->unique();
        $table->string('name', 150);
        $table->string('education_level', 50)->nullable();
        $table->string('district', 100)->nullable();
        $table->string('region_code', 20)->nullable();
        $table->boolean('is_active');
        $table->timestamp('source_updated_at');
        $table->timestamps();
    });
});

it('syncs master school API records into local projection', function (): void {
    config()->set('services.master.url', 'https://master.test');
    config()->set('services.master.token', 'consumer-token');
    Http::fake([
        'https://master.test/api/v1/schools*' => Http::response([
            'data' => [[
                'public_id' => '019fb54b-8bbc-70c9-afcd-47b68d92b83d',
                'npsn' => '12345678',
                'name' => 'SMA Negeri 1',
                'education_level' => 'SMA',
                'district' => 'Kuningan',
                'region_code' => '32.08',
                'is_active' => true,
                'updated_at' => '2026-07-31T00:00:00.000000Z',
            ]],
            'links' => ['next' => null],
        ]),
    ]);

    $this->artisan('master:sync-schools')->assertSuccessful();

    expect(DB::table('master_school_projections')->first())
        ->npsn->toBe('12345678')
        ->name->toBe('SMA Negeri 1');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer consumer-token'));
});

it('fails closed when master consumer configuration is absent', function (): void {
    config()->set('services.master.url', null);
    config()->set('services.master.token', null);

    $this->artisan('master:sync-schools')->assertFailed();

    expect(DB::table('master_school_projections')->count())->toBe(0);
});
