<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class DeviceApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeSite(array $overrides = []): Site
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'code' => 'S-'.strtoupper(Str::random(5)),
            'name' => 'Site',
            'is_active' => true,
            'timezone' => 'Africa/Kigali',
        ];

        return Site::query()->create(array_merge($defaults, $overrides));
    }

    private function makeDevice(array $overrides = []): Device
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'serial_number' => 'DEV'.strtoupper(Str::random(6)),
            'status' => Device::STATUS_PENDING,
            'capabilities' => [],
        ];

        return Device::query()->create(array_merge($defaults, $overrides));
    }

    public function test_guest_cannot_list_devices(): void
    {
        $this->getJsonApi('/api/v1/devices')->assertUnauthorized();
    }

    public function test_admin_can_list_and_filter_devices(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $this->makeDevice([
            'serial_number' => 'LIST001',
            'status' => Device::STATUS_APPROVED,
            'site_id' => $site->id,
            'approved_at' => now(),
        ]);
        $this->makeDevice([
            'serial_number' => 'LIST002',
            'status' => Device::STATUS_PENDING,
            'site_id' => $site->id,
        ]);

        $this->getJsonApi('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'devices');

        $this->getJsonApi('/api/v1/devices?filter[status]=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.serialNumber', 'LIST001');
    }

    public function test_admin_can_create_device_under_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'HQ']);

        $response = $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices', [
            'data' => [
                'type' => 'devices',
                'attributes' => [
                    'serialNumber' => 'NEWDEV01',
                    'deviceName' => 'Front Door',
                ],
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'devices')
            ->assertJsonPath('data.attributes.serialNumber', 'NEWDEV01')
            ->assertJsonPath('data.attributes.status', Device::STATUS_PENDING);

        $this->assertDatabaseHas('devices', [
            'serial_number' => 'NEWDEV01',
            'site_id' => $site->id,
        ]);
    }

    public function test_show_device_by_serial_number(): void
    {
        $this->actingAsApiUser();
        $this->makeDevice([
            'serial_number' => 'BYSERIAL',
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->getJsonApi('/api/v1/devices/BYSERIAL')
            ->assertOk()
            ->assertJsonPath('data.attributes.serialNumber', 'BYSERIAL');
    }

    public function test_admin_can_approve_and_block_device(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeDevice([
            'serial_number' => 'APPR01',
            'status' => Device::STATUS_PENDING,
        ]);

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/approve')
            ->assertOk()
            ->assertJsonPath('data.attributes.status', Device::STATUS_APPROVED);

        $this->assertNotNull($device->fresh()->approved_at);

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/block', [
            'data' => [
                'attributes' => [
                    'rejectionReason' => 'Decommissioned',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.status', Device::STATUS_BLOCKED)
            ->assertJsonPath('data.attributes.rejectionReason', 'Decommissioned');
    }

    public function test_admin_can_update_and_delete_device(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $device = $this->makeDevice([
            'serial_number' => 'UPDDEV1',
            'device_name' => 'Old Name',
            'site_id' => $site->id,
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->patchJsonApi('/api/v1/sites/'.$site->id.'/devices/'.$device->serial_number, [
            'data' => [
                'type' => 'devices',
                'attributes' => [
                    'deviceName' => 'New Name',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.deviceName', 'New Name');

        $this->deleteJsonApi('/api/v1/devices/'.$device->serial_number)->assertNoContent();
        $this->assertSoftDeleted('devices', ['serial_number' => 'UPDDEV1']);
    }

    public function test_viewer_cannot_approve_device(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);
        $device = $this->makeDevice([
            'serial_number' => 'VIEW01',
            'status' => Device::STATUS_PENDING,
        ]);

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/approve')
            ->assertForbidden();
    }
}
