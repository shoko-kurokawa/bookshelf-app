<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class FortifyServiceProviderTest extends TestCase
{
    /**
     * ログイン画面が表示できる
     */
    public function test_login_view_is_accessible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    /**
     * ユーザー登録画面が表示できる
     */
    public function test_register_view_is_accessible(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertViewIs('auth.register');
    }

    /**
     * ログイン試行の制限が正しく設定されている
     */
    public function test_login_rate_limiter_is_configured(): void
    {
        $request = Request::create(
            '/login',
            'POST',
            [Fortify::username() => 'TEST@EXAMPLE.COM'],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $limiter = RateLimiter::limiter('login');

        $this->assertNotNull($limiter);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame(1, $limit->decayMinutes);
        $this->assertSame(
            'test@example.com|127.0.0.1',
            $limit->key
        );
    }

    /**
     * 二要素認証の試行制限が正しく設定されている
     */
    public function test_two_factor_rate_limiter_is_configured(): void
    {
        $request = Request::create('/two-factor-challenge', 'POST');

        $request->setLaravelSession(
            app('session')->driver()
        );

        $request->session()->put('login.id', 123);

        $limiter = RateLimiter::limiter('two-factor');

        $this->assertNotNull($limiter);

        $limit = $limiter($request);

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(5, $limit->maxAttempts);
        $this->assertSame(1, $limit->decayMinutes);
        $this->assertSame('123', (string) $limit->key);
    }
}
