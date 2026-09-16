<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    public function isEnabled(): bool
    {
        if (! (bool) config('services.turnstile.enabled')) {
            return false;
        }

        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->isEnabled()) {
            return app()->isLocal() || app()->runningUnitTests();
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]
            );

            return (bool) $response->json('success');
        } catch (\Throwable $e) {
            Log::warning('Verifikasi Turnstile gagal: '.$e->getMessage());

            return false;
        }
    }
}
