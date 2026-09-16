<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    public function test_login_with_username_returns_bearer_token(): void
    {
        $this->makeUser([
            'username' => 'apitest',
            'email' => 'apitest@example.com',
            'phone_number' => '0788111222',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Admin,
        ]);

        $response = $this->postJsonApi('/api/v1/login', [
            'login' => 'apitest',
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'tokens')
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => ['token', 'tokenType', 'abilities'],
                ],
                'included',
            ]);

        $this->assertSame('Bearer', $response->json('data.attributes.tokenType'));
        $this->assertNotEmpty($response->json('data.attributes.token'));
    }

    public function test_login_with_email_works(): void
    {
        $this->makeUser([
            'username' => 'emailuser',
            'email' => 'email.user@example.com',
            'phone_number' => '0788333444',
            'password' => bcrypt('password'),
        ]);

        $this->postJsonApi('/api/v1/login', [
            'login' => 'email.user@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.type', 'tokens');
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $this->makeUser([
            'username' => 'badlogin',
            'email' => 'bad@example.com',
            'phone_number' => '0788555666',
            'password' => bcrypt('password'),
        ]);

        $this->postJsonApi('/api/v1/login', [
            'login' => 'badlogin',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJsonApi('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->actingAsApiUser();

        $this->getJsonApi('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->makeUser(['username' => 'logoutme', 'password' => bcrypt('password')]);

        $login = $this->postJsonApi('/api/v1/login', [
            'login' => 'logoutme',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('data.attributes.token');

        $this->withHeaders(array_merge($this->apiHeaders(), [
            'Authorization' => 'Bearer '.$token,
        ]))->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->withHeaders(array_merge($this->apiHeaders(), [
            'Authorization' => 'Bearer '.$token,
        ]))->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_logout_all_revokes_all_tokens(): void
    {
        $user = $this->makeUser();
        $user->createToken('a');
        $user->createToken('b');

        $this->assertSame(2, $user->tokens()->count());

        Sanctum::actingAs($user, ['*']);

        $this->postJsonApi('/api/v1/logout-all')->assertNoContent();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
