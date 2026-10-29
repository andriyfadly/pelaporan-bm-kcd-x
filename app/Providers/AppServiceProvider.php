<?php

namespace App\Providers;

use App\Listeners\CatatLoginLogout;
use App\Models\Master\Pegawai;
use App\Models\Master\Ruang;
use App\Models\PencatatanBm\InventarisUnit;
use App\Policies\InventarisPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        // Tanggal selalu bahasa Indonesia. `format('d F Y')` memakai nama bulan
        // PHP (selalu Inggris) dan `translatedFormat`/`isoFormat` ikut locale
        // Carbon; APP_LOCALE tidak bisa dijadikan patokan karena phpunit.xml
        // menguncinya ke `en` dan pesan validasi Indonesia dipilih lewat
        // `lang/id`. Dokumen resmi tidak boleh tercetak "30 January 2026".
        Carbon::setLocale('id');

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Gate::define('viewLogViewer', fn ($user) => $user->can('lihat-log-aktivitas'));

        Gate::policy(InventarisUnit::class, InventarisPolicy::class);
        Gate::policy(Ruang::class, InventarisPolicy::class);
        Gate::policy(Pegawai::class, InventarisPolicy::class);

        Event::listen(Login::class, CatatLoginLogout::class);
        Event::listen(Logout::class, CatatLoginLogout::class);
    }
}
