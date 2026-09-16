<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileLoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(): User
    {
        return User::create([
            'name' => 'Operator Turnstile',
            'username' => 'operator_turnstile',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_login_lolos_saat_turnstile_nonaktif_di_local(): void
    {
        config(['services.turnstile.enabled' => false]);
        $this->buatUser();

        $this->post('/login', ['username' => 'operator_turnstile', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_gagal_tanpa_token_saat_turnstile_aktif(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);
        $this->buatUser();

        $this->post('/login', ['username' => 'operator_turnstile', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_lolos_dengan_token_valid(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);
        $this->buatUser();

        $this->post('/login', [
            'username' => 'operator_turnstile',
            'password' => 'password',
            'cf-turnstile-response' => 'token-valid',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_service_skip_saat_key_kosong_di_local(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $this->assertTrue(app(TurnstileService::class)->verify(null));
    }
}
