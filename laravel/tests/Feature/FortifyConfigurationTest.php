<?php

use App\Models\User;

it('uses username login without public registration or recovery routes', function (): void {
    expect(config('fortify.username'))->toBe('username')
        ->and(config('fortify.features'))->toBe([]);

    $this->get('/login')->assertSuccessful();
    $this->get('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
});

it('maps authentication to the legacy users table safely', function (): void {
    $user = new User;

    expect($user->getTable())->toBe('users')
        ->and($user->usesTimestamps())->toBeFalse()
        ->and($user->isFillable('username'))->toBeFalse()
        ->and($user->isFillable('role'))->toBeFalse()
        ->and($user->isFillable('id_sekolah'))->toBeFalse()
        ->and($user->getAuthPasswordName())->toBe('password');
});

it('rate limits login by both account and ip address', function (): void {
    $limiter = app('Illuminate\Cache\RateLimiter')->limiter('login');
    $limits = $limiter(request()->merge(['username' => 'operator']));

    expect($limits)->toBeArray()->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(5)
        ->and($limits[1]->maxAttempts)->toBe(20);
});
