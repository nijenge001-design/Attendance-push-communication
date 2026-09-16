<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class DeviceAssignApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeSite(array $overrides = []): Site
    {
        return Site::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'S-'.strtoupper(Str::random(5)),
            'name' => 'Site',
            'is_active' => true,
            'timezone' => 'Africa/Kigali',
        ], $overrides));
    }

    private function makeDevice(array $overrides = []): Device
    {
        return Device::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'serial_number' => 'DEV'.strtoupper(Str::random(6)),
            'status' => Device::STATUS_PENDING,
            'capabilities' => [],
        ], $overrides));
    }

    public function test_admin_can_assign_unassigned_device_to_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'HQ']);
        $device = $this->makeDevice(['serial_number' => 'UNSITED1']);

        $this->assertNull($device->site_id);

        $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices/'.$device->serial_number.'/assign')
            ->assertOk()
            ->assertJsonPath('data.attributes.serialNumber', 'UNSITED1')
            ->assertJsonPath('data.attributes.siteId', $site->id);

        $this->assertSame($site->id, $device->fresh()->site_id);
    }

    public function test_admin_can_assign_site_via_body(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'MUS']);
        $device = $this->makeDevice(['serial_number' => 'BODYASN1']);

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/assign-site', [
            'data' => [
                'type' => 'devices',
                'attributes' => [
                    'siteId' => $site->id,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.siteId', $site->id);

        $this->assertSame($site->id, $device->fresh()->site_id);
    }

    public function test_admin_can_move_device_to_another_site(): void
    {
        $this->actingAsApiUser();
        $from = $this->makeSite(['code' => 'FROM']);
        $to = $this->makeSite(['code' => 'TO']);
        $device = $this->makeDevice([
            'serial_number' => 'MOVE01',
            'site_id' => $from->id,
        ]);

        $this->postJsonApi('/api/v1/sites/'.$to->id.'/devices/'.$device->serial_number.'/assign')
            ->assertOk()
            ->assertJsonPath('data.attributes.siteId', $to->id);

        $this->assertSame($to->id, $device->fresh()->site_id);
    }

    public function test_cannot_assign_to_inactive_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'DEAD', 'is_active' => false]);
        $device = $this->makeDevice(['serial_number' => 'NOPE01']);

        $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices/'.$device->serial_number.'/assign')
            ->assertStatus(422);
    }

    public function test_guest_cannot_assign_device(): void
    {
        $site = $this->makeSite();
        $device = $this->makeDevice();

        $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices/'.$device->serial_number.'/assign')
            ->assertUnauthorized();
    }

    public function test_admin_can_bulk_assign_devices_to_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'BULK']);
        $a = $this->makeDevice(['serial_number' => 'BULK01']);
        $b = $this->makeDevice(['serial_number' => 'BULK02', 'site_id' => $site->id]);

        $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices/bulk-assign', [
            'data' => [
                'type' => 'device-assignments',
                'attributes' => [
                    'serialNumbers' => ['BULK01', 'BULK02', 'MISSING'],
                ],
            ],
        ])->assertStatus(207)
            ->assertJsonPath('data.attributes.updated', 1)
            ->assertJsonPath('data.attributes.skipped', 1)
            ->assertJsonPath('data.attributes.failed', 1);

        $this->assertSame($site->id, $a->fresh()->site_id);
        $this->assertSame($site->id, $b->fresh()->site_id);
    }

    public function test_admin_can_bulk_assign_via_items(): void
    {
        $this->actingAsApiUser();
        $hq = $this->makeSite(['code' => 'HQ2']);
        $mus = $this->makeSite(['code' => 'MUS2']);
        $a = $this->makeDevice(['serial_number' => 'ITEM01']);
        $b = $this->makeDevice(['serial_number' => 'ITEM02']);

        $this->postJsonApi('/api/v1/devices/bulk-assign', [
            'data' => [
                'type' => 'device-assignments',
                'attributes' => [
                    'items' => [
                        ['serialNumber' => 'ITEM01', 'siteId' => $hq->id],
                        ['serialNumber' => 'ITEM02', 'siteId' => $mus->id],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.updated', 2);

        $this->assertSame($hq->id, $a->fresh()->site_id);
        $this->assertSame($mus->id, $b->fresh()->site_id);
    }

    public function test_bulk_assign_rejects_inactive_site(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite(['code' => 'OFF', 'is_active' => false]);
        $this->makeDevice(['serial_number' => 'OFFDEV1']);

        $this->postJsonApi('/api/v1/sites/'.$site->id.'/devices/bulk-assign', [
            'data' => [
                'attributes' => [
                    'serialNumbers' => ['OFFDEV1'],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('data.attributes.failed', 1);
    }
}
