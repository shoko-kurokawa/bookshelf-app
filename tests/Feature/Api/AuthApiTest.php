<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しい認証情報でログインするとトークンが発行される。
     */
    public function test_user_can_login_and_receive_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'ログインしました。')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'bookshelf-api',
        ]);
    }

    /**
     * パスワードが間違っている場合は401。
     */
    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * 存在しないメールアドレスではログインできない。
     */
    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * 必須項目が不足すると422。
     */
    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }

    /**
     * 発行したBearerトークンで認証付きAPIにアクセスできる。
     */
    public function test_api_token_can_authenticate_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    /**
     * ログアウトすると使用中のトークンが失効する。
     */
    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'ログアウトしました。');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // テスト内に残った認証状態をクリアする
        auth()->forgetGuards();

        // 失効したトークンでは認証できないことを検証
        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    /**
     * 未認証ユーザーはログアウトできない。
     */
    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertUnauthorized();
    }
}
