<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

trait InteractsWithApi
{
    /**
     * JSON:API request headers.
     *
     * @return array<string, string>
     */
    protected function apiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }

    /**
     * Create a user (admin by default) and authenticate via Sanctum.
     */
    protected function actingAsApiUser(?User $user = null, UserRole $role = UserRole::Admin): User
    {
        $user ??= $this->makeUser(['role' => $role]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeUser(array $overrides = []): User
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'username' => 'user_'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.com',
            'phone_number' => '078'.fake()->unique()->numerify('#######'),
            'password' => bcrypt('password'),
            'role' => UserRole::Admin,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ];

        if (method_exists(User::class, 'factory')) {
            try {
                return User::factory()->create(array_merge($defaults, $overrides));
            } catch (\Throwable) {
                // fall through
            }
        }

        return User::query()->create(array_merge($defaults, $overrides));
    }

    protected function getJsonApi(string $uri, array $headers = [])
    {
        return $this->withHeaders(array_merge($this->apiHeaders(), $headers))
            ->getJson($uri);
    }

    protected function postJsonApi(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders(array_merge($this->apiHeaders(), $headers))
            ->postJson($uri, $data);
    }

    protected function patchJsonApi(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders(array_merge($this->apiHeaders(), $headers))
            ->patchJson($uri, $data);
    }

    protected function putJsonApi(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders(array_merge($this->apiHeaders(), $headers))
            ->putJson($uri, $data);
    }

    protected function deleteJsonApi(string $uri, array $headers = [])
    {
        return $this->withHeaders(array_merge($this->apiHeaders(), $headers))
            ->deleteJson($uri);
    }
}
