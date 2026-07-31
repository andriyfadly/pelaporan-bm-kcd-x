<?php

namespace App\Providers;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('username', $request->string('username'))->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if ($user->role !== 'admin' && ! Sekolah::query()->whereKey($user->id_sekolah)->exists()) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request): array {
            $username = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            return [
                Limit::perMinute(5)->by('account|'.$username),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
            ];
        });
    }
}
