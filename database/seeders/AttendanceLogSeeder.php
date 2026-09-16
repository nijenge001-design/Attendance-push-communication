<?php

namespace Database\Seeders;


use App\Models\AttendanceLog;
use App\Models\Attendee;
use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttendanceLogSeeder extends Seeder
{
    public function run(): void
    {
        $devices = Device::query()
            ->where('status', Device::STATUS_APPROVED)
            ->get();

        $pins = Attendee::query()->pluck('pin')->all();

        if ($devices->isEmpty() || $pins === []) {
            $this->command?->warn('AttendanceLogSeeder skipped: need approved devices and attendees');

            return;
        }

        $created = 0;
        $days = 14;

        foreach ($devices as $device) {
            foreach ($pins as $pin) {
                // ~60% chance of attendance per day
                for ($d = $days; $d >= 0; $d--) {
                    if (fake()->boolean(40)) {
                        continue;
                    }

                    $date = now()->subDays($d)->startOfDay();

                    // Check-in morning
                    $inAt = $date->copy()->setTime(
                        fake()->numberBetween(7, 9),
                        fake()->numberBetween(0, 59),
                        fake()->numberBetween(0, 59)
                    );

                    AttendanceLog::query()->firstOrCreate(
                        [
                            'device_serial' => $device->serial_number,
                            'pin' => $pin,
                            'timestamp' => $inAt,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'status' => 0,
                            'verify_mode' => fake()->randomElement([1, 3, 4, 15]),
                            'type' => 0,
                            'mask_flag' => fake()->optional(0.1)->randomElement([0, 1]),
                            'temperature' => fake()->optional(0.1)->randomFloat(2, 36.0, 37.5),
                        ]
                    );
                    $created++;

                    // Check-out evening (~80% of days with check-in)
                    if (fake()->boolean(80)) {
                        $outAt = $date->copy()->setTime(
                            fake()->numberBetween(16, 19),
                            fake()->numberBetween(0, 59),
                            fake()->numberBetween(0, 59)
                        );

                        AttendanceLog::query()->firstOrCreate(
                            [
                                'device_serial' => $device->serial_number,
                                'pin' => $pin,
                                'timestamp' => $outAt,
                            ],
                            [
                                'id' => (string) Str::uuid(),
                                'status' => 1,
                                'verify_mode' => fake()->randomElement([1, 3, 4, 15]),
                                'type' => 0,
                            ]
                        );
                        $created++;
                    }
                }
            }
        }

        $this->command?->info("Attendance logs seeded (~{$created} rows attempted)");
    }
}
