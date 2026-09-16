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
            Log::warning('Turnstile dilewati/ditolak: konfigurasi tidak lengkap', [
                'enabled_flag' => (bool) config('services.turnstile.enabled'),
                'site_key_terisi' => filled(config('services.turnstile.site_key')),
                'secret_key_terisi' => filled(config('services.turnstile.secret_key')),
                'env' => app()->environment(),
            ]);

            return app()->isLocal() || app()->runningUnitTests();
        }

        if (blank($token)) {
            Log::warning('Verifikasi Turnstile gagal: token kosong dari client.');

            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]
            );

            $body = $response->json();
            $success = (bool) ($body['success'] ?? false);

            if (! $success) {
                Log::warning('Verifikasi Turnstile ditolak Cloudflare', [
                    'http_status' => $response->status(),
                    'error_codes' => $body['error-codes'] ?? null,
                    'hostname' => $body['hostname'] ?? null,
                    'token_len' => strlen($token ?? ''),
                    'token_prefix' => substr((string) $token, 0, 12),
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Verifikasi Turnstile gagal: '.$e->getMessage());

            return false;
        }
    }
}
