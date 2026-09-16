<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class SiteApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeSite(array $overrides = []): Site
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'code' => 'SITE-'.strtoupper(Str::random(4)),
            'name' => 'Test Site',
            'city' => 'Kigali',
            'country' => 'Rwanda',
            'timezone' => 'Africa/Kigali',
            'is_active' => true,
        ];

        if (method_exists(Site::class, 'factory')) {
            try {
                return Site::factory()->create(array_merge($defaults, $overrides));
            } catch (\Throwable) {
            }
        }

        return Site::query()->create(array_merge($defaults, $overrides));
    }

    public function test_guest_cannot_list_sites(): void
    {
        $this->getJsonApi('/api/v1/sites')->assertUnauthorized();
    }

    public function test_admin_can_list_sites(): void
    {
        $this->actingAsApiUser();
        $this->makeSite(['code' => 'HQ-TEST']);

        $this->getJsonApi('/api/v1/sites')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'sites');
    }

    public function test_admin_can_create_site(): void
    {
        $this->actingAsApiUser();

        $response = $this->postJsonApi('/api/v1/sites', [
            'data' => [
                'type' => 'sites',
                'attributes' => [
                    'code' => 'NEW-HQ',
                    'name' => 'New Head Office',
                    'city' => 'Kigali',
                    'country' => 'Rwanda',
                    'timezone' => 'Africa/Kigali',
                    'isActive' => true,
                ],
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'sites')
            ->assertJsonPath('data.attributes.code', 'NEW-HQ')
            ->assertJsonPath('data.attributes.name', 'New Head Office');

        $this->assertDatabaseHas('sites', ['code' => 'NEW-HQ']);
    }

    public function test_admin_can_show_update_and_delete_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'UPD-1', 'name' => 'Before']);

        $this->getJsonApi('/api/v1/sites/'.$site->id)
            ->assertOk()
            ->assertJsonPath('data.attributes.code', 'UPD-1');

        $this->patchJsonApi('/api/v1/sites/'.$site->id, [
            'data' => [
                'type' => 'sites',
                'id' => $site->id,
                'attributes' => [
                    'name' => 'After',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.name', 'After');

        $this->deleteJsonApi('/api/v1/sites/'.$site->id)->assertNoContent();

        $this->assertSoftDeleted('sites', ['id' => $site->id]);
    }

    public function test_viewer_cannot_create_site(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);

        $this->postJsonApi('/api/v1/sites', [
            'data' => [
                'type' => 'sites',
                'attributes' => [
                    'code' => 'NOPE',
                    'name' => 'Forbidden',
                ],
            ],
        ])->assertForbidden();
    }
}
