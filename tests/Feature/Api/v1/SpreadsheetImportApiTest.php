<?php

declare(strict_types=1);

namespace Tests\Feature\Api\v1;

use App\Enums\UserRole;
use App\Models\Attendee;
use App\Models\Device;
use App\Models\Site;
use App\Services\Imports\SpreadsheetWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

final class SpreadsheetImportApiTest extends TestCase
{
    use InteractsWithApi;
    use RefreshDatabase;

    public function test_csv_import_creates_attendees(): void
    {
        $this->actingAsApiUser();

        $csv = "pin,name,cardNumber\n8101,Ada Lovelace,CARD81\n8102,Alan Turing,\n";
        $file = UploadedFile::fake()->createWithContent('staff.csv', $csv);

        $this->post('/api/v1/attendees/import', [
            'mode' => 'upsert',
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertJsonPath('data.type', 'bulk-results')
            ->assertJsonPath('data.attributes.created', 2);

        $this->assertDatabaseHas('attendees', ['pin' => '8101', 'name' => 'Ada Lovelace']);
        $this->assertDatabaseHas('attendees', ['pin' => '8102', 'name' => 'Alan Turing']);
    }

    public function test_xlsx_import_round_trip(): void
    {
        $this->actingAsApiUser();

        $xlsx = (new SpreadsheetWriter)->xlsx(
            ['Employee ID', 'Full Name'],
            [['8201', 'Grace Hopper']],
        );
        $file = UploadedFile::fake()->createWithContent('staff.xlsx', $xlsx);

        $this->post('/api/v1/attendees/import', [
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertJsonPath('data.attributes.created', 1);

        $this->assertDatabaseHas('attendees', ['pin' => '8201', 'name' => 'Grace Hopper']);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->actingAsApiUser();

        Attendee::query()->create([
            'id' => (string) Str::uuid(),
            'pin' => '8301',
            'name' => 'Existing',
            'privilege' => 0,
            'group_id' => 1,
            'verification_mode' => -1,
        ]);

        $csv = "pin,name\n8301,Would Update\n8302,Would Create\n";
        $file = UploadedFile::fake()->createWithContent('preview.csv', $csv);

        $this->post('/api/v1/attendees/import', [
            'dryRun' => '1',
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertJsonPath('data.id', 'attendees-import-dry-run')
            ->assertJsonPath('data.attributes.created', 1)
            ->assertJsonPath('data.attributes.updated', 1);

        $this->assertDatabaseHas('attendees', ['pin' => '8301', 'name' => 'Existing']);
        $this->assertDatabaseMissing('attendees', ['pin' => '8302']);
    }

    public function test_missing_pin_column_is_rejected(): void
    {
        $this->actingAsApiUser();

        $file = UploadedFile::fake()->createWithContent('bad.csv', "name,card\nNo Pin,X\n");

        $this->post('/api/v1/attendees/import', [
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.title', 'Invalid Spreadsheet');
    }

    public function test_viewer_cannot_import(): void
    {
        $this->actingAsApiUser(role: UserRole::Viewer);

        $file = UploadedFile::fake()->createWithContent('staff.csv', "pin,name\n1,A\n");

        $this->post('/api/v1/attendees/import', [
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertForbidden();
    }

    public function test_template_download(): void
    {
        $this->actingAsApiUser();

        $this->get('/api/v1/attendees/import-template?format=csv', [
            'Accept' => 'text/csv',
        ])->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_attendance_csv_import(): void
    {
        Queue::fake();
        $this->actingAsApiUser();

        $site = Site::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'IMP'.strtoupper(Str::random(3)),
            'name' => 'Import Site',
            'is_active' => true,
        ]);
        Device::query()->create([
            'id' => (string) Str::uuid(),
            'serial_number' => 'XLSXDEV1',
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
            'site_id' => $site->id,
            'capabilities' => [],
        ]);

        $csv = "pin,timestamp,status,verifyMode\n8401,2026-09-12 08:00:00,0,1\n";
        $file = UploadedFile::fake()->createWithContent('punches.csv', $csv);

        $this->post('/api/v1/attendance-logs/import', [
            'deviceSerial' => 'XLSXDEV1',
            'dispatchJobs' => '0',
            'file' => $file,
        ], ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertJsonPath('data.attributes.created', 1);

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '8401',
            'device_serial' => 'XLSXDEV1',
        ]);
    }
}
