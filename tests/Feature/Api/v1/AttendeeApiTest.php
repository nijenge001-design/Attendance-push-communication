<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Models\Attendee;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class AttendeeApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeSite(): Site
    {
        return Site::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'AT-'.strtoupper(Str::random(4)),
            'name' => 'Attendee Site',
            'is_active' => true,
        ]);
    }

    private function makeDevice(array $overrides = []): Device
    {
        return Device::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'serial_number' => 'ATD'.strtoupper(Str::random(5)),
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
            'capabilities' => [],
        ], $overrides));
    }

    private function makeAttendee(array $overrides = []): Attendee
    {
        return Attendee::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'pin' => (string) fake()->unique()->numberBetween(2000, 8999),
            'name' => 'Employee',
            'privilege' => 0,
            'group_id' => 1,
            'verification_mode' => -1,
        ], $overrides));
    }

    public function test_guest_cannot_list_attendees(): void
    {
        $this->getJsonApi('/api/v1/attendees')->assertUnauthorized();
    }

    public function test_admin_can_create_and_list_attendees(): void
    {
        $this->actingAsApiUser();

        $response = $this->postJsonApi('/api/v1/attendees', [
            'data' => [
                'type' => 'attendees',
                'attributes' => [
                    'pin' => '5555',
                    'name' => 'Jane Doe',
                    'privilege' => 0,
                    'groupId' => 1,
                ],
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'attendees')
            ->assertJsonPath('data.attributes.pin', '5555')
            ->assertJsonPath('data.attributes.name', 'Jane Doe');

        $this->getJsonApi('/api/v1/attendees?filter[pin]=5555')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.pin', '5555');
    }

    public function test_admin_can_update_and_delete_attendee(): void
    {
        $this->actingAsApiUser();
        $attendee = $this->makeAttendee(['pin' => '6666', 'name' => 'Old']);

        $this->patchJsonApi('/api/v1/attendees/'.$attendee->id, [
            'data' => [
                'type' => 'attendees',
                'attributes' => [
                    'name' => 'Updated Name',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.name', 'Updated Name');

        $this->deleteJsonApi('/api/v1/attendees/'.$attendee->id)->assertNoContent();
        $this->assertSoftDeleted('attendees', ['pin' => '6666']);
    }

    public function test_grant_and_revoke_device_access(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $device = $this->makeDevice(['site_id' => $site->id, 'serial_number' => 'GRANT01']);
        $attendee = $this->makeAttendee(['pin' => '7777']);

        $this->postJsonApi('/api/v1/attendees/'.$attendee->id.'/grant-device-access', [
            'data' => [
                'attributes' => [
                    'deviceSerial' => 'GRANT01',
                    'privilege' => 0,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('attendee_device', [
            'attendee_id' => $attendee->id,
            'device_serial' => 'GRANT01',
        ]);

        $this->postJsonApi('/api/v1/attendees/'.$attendee->id.'/revoke-device-access', [
            'data' => [
                'attributes' => [
                    'deviceSerial' => 'GRANT01',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('attendee_device', [
            'attendee_id' => $attendee->id,
            'device_serial' => 'GRANT01',
        ]);
    }

    public function test_viewer_cannot_create_attendee(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);

        $this->postJsonApi('/api/v1/attendees', [
            'data' => [
                'type' => 'attendees',
                'attributes' => [
                    'pin' => '9999',
                    'name' => 'No Access',
                ],
            ],
        ])->assertForbidden();
    }
}
