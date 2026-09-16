<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Models\Device;
use App\Models\PendingCommand;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class PendingCommandApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeApprovedDevice(string $serial = 'CMDDEV1'): Device
    {
        $site = Site::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'C-'.strtoupper(Str::random(4)),
            'name' => 'Cmd Site',
            'is_active' => true,
        ]);

        return Device::query()->create([
            'id' => (string) Str::uuid(),
            'serial_number' => $serial,
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
            'site_id' => $site->id,
            'capabilities' => [],
        ]);
    }

    public function test_guest_cannot_list_commands(): void
    {
        $this->getJsonApi('/api/v1/pending-commands')->assertUnauthorized();
    }

    public function test_admin_can_queue_command_for_device(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeApprovedDevice('QUEUE01');

        $response = $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/commands', [
            'data' => [
                'type' => 'pending-commands',
                'attributes' => [
                    'commandText' => 'DATA QUERY USERINFO Pin=1001',
                ],
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'pending-commands');

        $this->assertDatabaseHas('pending_commands', [
            'device_serial' => 'QUEUE01',
        ]);

        $body = PendingCommand::query()->where('device_serial', 'QUEUE01')->value('command_text')
            ?? PendingCommand::query()->where('device_serial', 'QUEUE01')->value('command')
            ?? '';

        $this->assertStringContainsString('USERINFO', (string) $body);
    }

    public function test_admin_can_list_pending_commands(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeApprovedDevice('LISTCMD1');

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/commands', [
            'data' => [
                'attributes' => [
                    'commandText' => 'CHECK',
                ],
            ],
        ])->assertSuccessful();

        $this->getJsonApi('/api/v1/pending-commands?filter[device]=LISTCMD1')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'pending-commands');
    }
}
