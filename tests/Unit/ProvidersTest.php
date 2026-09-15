<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\PeranDanHakAksesSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranDanHakAksesSeeder::class);
    }

    public function test_gate_before_grants_super_admin(): void
    {
        $super = User::create(['name' => 'Dev', 'username' => 'dev_gate', 'password' => bcrypt('x')]);
        $super->assignRole('super_admin');
        $biasa = User::create(['name' => 'Op', 'username' => 'op_gate', 'password' => bcrypt('x')]);
        $biasa->assignRole('operator_sekolah');

        $this->assertTrue(Gate::forUser($super)->allows('arbitrary-ability-xyz'));
        $this->assertFalse(Gate::forUser($biasa)->allows('arbitrary-ability-xyz'));
    }

    public function test_rate_limiters_defined(): void
    {
        $login = RateLimiter::limiter('login');
        $this->assertNotNull($login);
        $r = Request::create('/login', 'POST', ['username' => 'a', 'password' => 'b']);
        $this->assertInstanceOf(Limit::class, $login($r));

        $twoFactor = RateLimiter::limiter('two-factor');
        $req2 = Request::create('/two-factor-challenge', 'POST');
        $req2->setLaravelSession(app('session.store'));
        $this->assertInstanceOf(Limit::class, $twoFactor($req2));

        $passkeys = RateLimiter::limiter('passkeys');
        $req3 = Request::create('/passkeys/authenticate', 'POST');
        $req3->setLaravelSession(app('session.store'));
        $this->assertInstanceOf(Limit::class, $passkeys($req3));
    }
}
