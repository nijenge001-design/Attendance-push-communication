<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class UserApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $this->getJsonApi('/api/v1/users')->assertUnauthorized();
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAsApiUser();
        $this->makeUser([
            'username' => 'otheruser',
            'email' => 'other@example.com',
            'phone_number' => '0788999000',
            'role' => UserRole::Viewer,
        ]);

        $this->getJsonApi('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'users');
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAsApiUser();

        $response = $this->postJsonApi('/api/v1/users', [
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'name' => 'New Operator',
                    'username' => 'newop',
                    'email' => 'newop@example.com',
                    'phoneNumber' => '0788123456',
                    'password' => 'password123',
                    'role' => UserRole::Operator->value,
                ],
            ],
        ]);

        // Controllers may return 200 or 201 depending on resource response
        $this->assertTrue(in_array($response->status(), [200, 201], true), (string) $response->getContent());
        $response->assertJsonPath('data.type', 'users');
        $this->assertDatabaseHas('users', ['username' => 'newop']);
    }

    public function test_viewer_cannot_manage_users(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);

        $this->getJsonApi('/api/v1/users')->assertForbidden();
    }
}
