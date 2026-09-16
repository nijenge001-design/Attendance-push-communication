<?php

namespace Database\Seeders;

use App\Models\Attendee;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttendeeSeeder extends Seeder
{
    public function run(): void
    {
        $hq = Site::query()->where('code', 'HQ-KGL')->first();
        $mus = Site::query()->where('code', 'BR-MUS')->first();

        $approvedSerials = Device::query()
            ->where('status', Device::STATUS_APPROVED)
            ->pluck('serial_number')
            ->all();

        // Fixed demo employees
        $fixed = [
            ['pin' => '1001', 'name' => 'Alice Uwase', 'privilege' => 0],
            ['pin' => '1002', 'name' => 'Jean Baptiste', 'privilege' => 0],
            ['pin' => '1003', 'name' => 'Grace Mukamana', 'privilege' => 0],
            ['pin' => '1004', 'name' => 'Eric Niyonsaba', 'privilege' => 0],
            ['pin' => '1005', 'name' => 'Claire Ingabire', 'privilege' => 0],
            ['pin' => '9001', 'name' => 'Site Supervisor', 'privilege' => 14],
        ];

        $attendees = [];
        foreach ($fixed as $row) {
            $attendees[] = Attendee::query()->updateOrCreate(
                ['pin' => $row['pin']],
                array_merge($row, [
                    'id' => (string) Str::uuid(),
                    'group_id' => 1,
                    'verification_mode' => -1,
                ])
            );
        }

        // Extra random employees
        $extra = Attendee::factory()->count(20)->create();
        $attendees = array_merge($attendees, $extra->all());

        // Attach to HQ site
        if ($hq) {
            foreach ($attendees as $attendee) {
                if (! $attendee->sites()->where('sites.id', $hq->id)->exists()) {
                    $attendee->sites()->attach($hq->id, [
                        'id' => (string) Str::uuid(),
                        'is_active' => true,
                        'granted_at' => now()->subDays(rand(1, 60)),
                    ]);
                }
            }
        }

        // Some also on Musanze
        if ($mus) {
            foreach (array_slice($attendees, 0, 8) as $attendee) {
                if (! $attendee->sites()->where('sites.id', $mus->id)->exists()) {
                    $attendee->sites()->attach($mus->id, [
                        'id' => (string) Str::uuid(),
                        'is_active' => true,
                        'granted_at' => now()->subDays(rand(1, 30)),
                    ]);
                }
            }
        }

        // Link attendees to approved devices (attendee_device)
        foreach ($attendees as $i => $attendee) {
            $serials = $approvedSerials === []
                ? []
                : (array) array_slice($approvedSerials, 0, min(2, count($approvedSerials)));

            // rotate primary device
            if ($approvedSerials !== []) {
                $serials = [ $approvedSerials[$i % count($approvedSerials)] ];
            }

            foreach ($serials as $serial) {
                if (! $attendee->devices()->where('devices.serial_number', $serial)->exists()) {
                    $attendee->devices()->attach($serial, [
                        'id' => (string) Str::uuid(),
                        'active' => true,
                        'privilege' => $attendee->privilege,
                        'group_id' => $attendee->group_id,
                    ]);
                }
            }
        }

        $this->command?->info('Attendees seeded: '.count($attendees));
    }
}
