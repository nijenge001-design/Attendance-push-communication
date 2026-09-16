<?php

declare(strict_types=1);

namespace Tests\Feature\Iclock;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Core iClock protocol endpoint smoke tests.
 */
final class CdataEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_returns_ok_for_known_device(): void
    {
        Device::factory()->create([
            'serial_number' => 'PING001',
            'status' => Device::STATUS_APPROVED,
        ]);

        $response = $this->get('/iclock/ping?SN=PING001');
        $response->assertOk();
        $this->assertSame('OK', trim($response->getContent()));
    }

    public function test_getrequest_returns_ok_or_commands_for_approved_device(): void
    {
        Device::factory()->create([
            'serial_number' => 'GR001',
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $response = $this->get('/iclock/getrequest?SN=GR001');
        $response->assertOk();
        // Either "OK" (no commands) or command lines starting with C:
        $body = trim($response->getContent());
        $this->assertTrue(
            $body === 'OK' || str_starts_with($body, 'C:'),
            "Unexpected getrequest body: {$body}"
        );
    }

    public function test_first_contact_creates_pending_device(): void
    {
        $this->assertDatabaseMissing('devices', ['serial_number' => 'NEW001']);

        $response = $this->get('/iclock/cdata?SN=NEW001&options=all&pushver=2.4.1');
        $response->assertOk();

        $this->assertDatabaseHas('devices', [
            'serial_number' => 'NEW001',
            'status' => Device::STATUS_PENDING,
        ]);
    }

    public function test_devicecmd_accepts_result_post(): void
    {
        Device::factory()->create([
            'serial_number' => 'CMD001',
            'status' => Device::STATUS_APPROVED,
        ]);

        // Minimal command result format used by many firmwares
        $payload = "ID=1&Return=0&CMD=DATA\n";

        $response = $this->call(
            'POST',
            '/iclock/devicecmd?SN=CMD001',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $payload
        );

        $response->assertOk();
    }
}
