<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Jobs\ProcessBulkAttendanceChunk;
use App\Models\AttendanceLog;
use App\Models\Attendee;
use App\Models\AttendeeImportError;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class BulkApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    private function makeSite(): Site
    {
        return Site::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'B-'.strtoupper(Str::random(4)),
            'name' => 'Bulk Site',
            'is_active' => true,
        ]);
    }

    private function makeDevice(Site $site, string $serial = 'BULKDEV1'): Device
    {
        return Device::query()->create([
            'id' => (string) Str::uuid(),
            'serial_number' => $serial,
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
            'site_id' => $site->id,
            'capabilities' => [],
        ]);
    }

    public function test_bulk_upsert_creates_and_updates_attendees(): void
    {
        $this->actingAsApiUser();

        Attendee::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '1001',
            'name' => 'Old Name',
            'privilege' => 0,
            'group_id' => 1,
            'verification_mode' => -1,
        ]);

        $response = $this->postJsonApi('/api/v1/attendees/bulk', [
            'data' => [
                'type' => 'bulk-results',
                'attributes' => [
                    'mode' => 'upsert',
                    'items' => [
                        ['pin' => '1001', 'name' => 'Updated'],
                        ['pin' => '1002', 'name' => 'New Person'],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'bulk-results')
            ->assertJsonPath('data.attributes.created', 1)
            ->assertJsonPath('data.attributes.updated', 1)
            ->assertJsonPath('data.attributes.failed', 0);

        $this->assertDatabaseHas('attendees', ['pin' => '1001', 'name' => 'Updated']);
        $this->assertDatabaseHas('attendees', ['pin' => '1002', 'name' => 'New Person']);
    }

    public function test_bulk_create_only_skips_existing(): void
    {
        $this->actingAsApiUser();

        Attendee::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '2001',
            'name' => 'Keep',
            'privilege' => 0,
            'group_id' => 1,
            'verification_mode' => -1,
        ]);

        $this->postJsonApi('/api/v1/attendees/bulk', [
            'data' => [
                'attributes' => [
                    'mode' => 'create-only',
                    'items' => [
                        ['pin' => '2001', 'name' => 'Should Skip'],
                        ['pin' => '2002', 'name' => 'Fresh'],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.created', 1)
            ->assertJsonPath('data.attributes.skipped', 1);

        $this->assertDatabaseHas('attendees', ['pin' => '2001', 'name' => 'Keep']);
    }

    public function test_viewer_cannot_bulk_upsert_attendees(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);

        $this->postJsonApi('/api/v1/attendees/bulk', [
            'data' => [
                'attributes' => [
                    'items' => [['pin' => '9', 'name' => 'Nope']],
                ],
            ],
        ])->assertForbidden();
    }

    public function test_bulk_attendance_inserts_and_skips_duplicates(): void
    {
        Queue::fake();
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $device = $this->makeDevice($site, 'BULKLOG1');

        $ts = now()->startOfMinute()->format('Y-m-d H:i:s');

        AttendanceLog::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '3001',
            'device_serial' => $device->serial_number,
            'timestamp' => $ts,
            'status' => 0,
            'verify_mode' => 1,
            'type' => 0,
        ]);

        $later = now()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');

        $this->postJsonApi('/api/v1/devices/'.$device->serial_number.'/attendance-logs/bulk', [
            'data' => [
                'attributes' => [
                    'items' => [
                        ['pin' => '3001', 'timestamp' => $ts, 'status' => 0, 'verifyMode' => 1],
                        ['pin' => '3002', 'timestamp' => $later, 'status' => 0, 'verifyMode' => 1],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.created', 1)
            ->assertJsonPath('data.attributes.skipped', 1);

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '3002',
            'device_serial' => 'BULKLOG1',
        ]);

        Queue::assertPushed(ProcessBulkAttendanceChunk::class);
    }

    public function test_mixed_device_bulk_requires_serial_per_item(): void
    {
        Queue::fake();
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $this->makeDevice($site, 'MIX01');

        $this->postJsonApi('/api/v1/attendance-logs/bulk', [
            'data' => [
                'attributes' => [
                    'dispatchJobs' => false,
                    'items' => [
                        [
                            'pin' => '4001',
                            'timestamp' => now()->toDateTimeString(),
                            'status' => 0,
                            'verifyMode' => 1,
                            'deviceSerial' => 'MIX01',
                        ],
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.created', 1);

        Queue::assertNothingPushed();
    }

    public function test_bulk_resolve_import_errors(): void
    {
        $this->actingAsApiUser();
        $site = $this->makeSite();
        $device = $this->makeDevice($site, 'IMP01');

        $error = AttendeeImportError::query()->create([
            'id' => (string) Str::uuid(),
            'device_serial' => $device->serial_number,
            'pin' => '5001',
            'error_code' => 'duplicate_pin',
            'error_message' => 'pin taken',
            'status' => AttendeeImportError::STATUS_PENDING,
        ]);

        $this->postJsonApi('/api/v1/attendee-import-errors/bulk', [
            'data' => [
                'attributes' => [
                    'action' => 'resolve',
                    'ids' => [$error->id],
                    'adminNote' => 'fixed in HR',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.attributes.updated', 1);

        $this->assertDatabaseHas('attendee_import_errors', [
            'id' => $error->id,
            'status' => AttendeeImportError::STATUS_RESOLVED,
        ]);
    }

    public function test_bulk_rejects_more_than_max_items(): void
    {
        $this->actingAsApiUser();

        $items = [];
        for ($i = 0; $i < 501; $i++) {
            $items[] = ['pin' => (string) (6000 + $i), 'name' => 'X'];
        }

        $this->postJsonApi('/api/v1/attendees/bulk', [
            'data' => [
                'attributes' => ['items' => $items],
            ],
        ])->assertUnprocessable();
    }
}
