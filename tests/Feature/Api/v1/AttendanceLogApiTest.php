<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class AttendanceLogApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeApprovedDevice(string $serial = 'LOGDEV1'): Device
    {
        $site = Site::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'L-'.strtoupper(Str::random(4)),
            'name' => 'Log Site',
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

    public function test_guest_cannot_list_attendance_logs(): void
    {
        $this->getJsonApi('/api/v1/attendance-logs')->assertUnauthorized();
    }

    public function test_admin_can_list_and_filter_logs(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeApprovedDevice('FILTER01');

        AttendanceLog::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '1001',
            'device_serial' => $device->serial_number,
            'timestamp' => now()->subHour(),
            'status' => 0,
            'verify_mode' => 1,
            'type' => 0,
        ]);

        AttendanceLog::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '1002',
            'device_serial' => $device->serial_number,
            'timestamp' => now()->subMinutes(30),
            'status' => 1,
            'verify_mode' => 15,
            'type' => 0,
        ]);

        $this->getJsonApi('/api/v1/attendance-logs')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'attendance-logs');

        $this->getJsonApi('/api/v1/attendance-logs?filter[pin]=1001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.pin', '1001');

        $this->getJsonApi('/api/v1/attendance-logs?filter[device]=FILTER01')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_store_log_for_device(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeApprovedDevice('STORE01');

        $ts = now()->format('Y-m-d H:i:s');

        $response = $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/attendance-logs', [
            'data' => [
                'type' => 'attendance-logs',
                'attributes' => [
                    'pin' => '2001',
                    'timestamp' => $ts,
                    'status' => 0,
                    'verifyMode' => 1,
                ],
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'attendance-logs')
            ->assertJsonPath('data.attributes.pin', '2001');

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '2001',
            'device_serial' => 'STORE01',
        ]);
    }

    public function test_admin_can_show_and_delete_log(): void
    {
        $this->actingAsApiUser();
        $device = $this->makeApprovedDevice('DELLOG1');

        $log = AttendanceLog::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '3001',
            'device_serial' => $device->serial_number,
            'timestamp' => now(),
            'status' => 0,
            'verify_mode' => 1,
            'type' => 0,
        ]);

        $this->getJsonApi('/api/v1/attendance-logs/'.$log->id)
            ->assertOk()
            ->assertJsonPath('data.id', $log->id);

        $this->deleteJsonApi('/api/v1/attendance-logs/'.$log->id)->assertNoContent();
        $this->assertSoftDeleted('attendance_logs', ['id' => $log->id]);
    }
}
