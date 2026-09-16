<?php

namespace Database\Seeders;

use App\Models\Attendee;
use App\Models\BioTemplate;
use App\Models\Device;
use App\Support\HybridBio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BioTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $device = Device::query()
            ->where('status', Device::STATUS_APPROVED)
            ->where('serial_number', 'CKJG0001')
            ->first()
            ?? Device::query()->where('status', Device::STATUS_APPROVED)->first();

        if (! $device) {
            $this->command?->warn('BioTemplateSeeder skipped: no approved device');

            return;
        }

        $pins = Attendee::query()->orderBy('pin')->limit(10)->pluck('pin');

        $count = 0;
        foreach ($pins as $pin) {
            // Fingerprint templates (2 fingers)
            foreach ([0, 5] as $index) {
                BioTemplate::query()->updateOrCreate(
                    [
                        'device_serial' => $device->serial_number,
                        'pin' => $pin,
                        'type' => HybridBio::TYPE_FINGERPRINT,
                        'index' => $index,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'no' => 0,
                        'valid' => 1,
                        'duress' => 0,
                        'major_ver' => '10',
                        'minor_ver' => '0',
                        'format' => '0',
                        'template_data' => base64_encode(random_bytes(48)),
                    ]
                );
                $count++;
            }

            // Visible light face
            BioTemplate::query()->updateOrCreate(
                [
                    'device_serial' => $device->serial_number,
                    'pin' => $pin,
                    'type' => HybridBio::TYPE_VISIBLE_FACE,
                    'index' => 0,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'no' => 0,
                    'valid' => 1,
                    'duress' => 0,
                    'major_ver' => '3',
                    'minor_ver' => '0',
                    'format' => '0',
                    'template_data' => base64_encode(random_bytes(96)),
                ]
            );
            $count++;
        }

        $this->command?->info("Bio templates seeded: {$count}");
    }
}
