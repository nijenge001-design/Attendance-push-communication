<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Site;
use App\Support\HybridBio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $hq = Site::query()->where('code', 'HQ-KGL')->first();
        $mus = Site::query()->where('code', 'BR-MUS')->first();
        $huy = Site::query()->where('code', 'BR-HUY')->first();

        $devices = [
            [
                'serial_number' => 'CKJG0001',
                'device_name' => 'HQ Entrance',
                'status' => Device::STATUS_APPROVED,
                'approved_at' => now()->subDays(30),
                'ip' => '192.168.1.101',
                'push_version' => '2.4.2',
                'firmware_version' => 'ZAM170-NF-Ver1.0',
                'platform' => 'ZMM220',
                'language' => '69',
                'finger_fun_on' => true,
                'face_fun_on' => true,
                'photo_fun_on' => true,
                'user_count' => 50,
                'fp_count' => 120,
                'face_count' => 40,
                'last_seen_at' => now()->subMinutes(2),
                'last_heartbeat_at' => now()->subMinutes(2),
                'last_heartbeat_source' => 'getrequest',
                'site_id' => $hq?->id,
                'capabilities' => [
                    'multi_bio_data_support' => '0:1:1:0:0:0:0:0:0:1:0',
                    'multi_bio_photo_support' => '0:0:0:0:0:0:0:0:0:1:0',
                    'multi_bio_version' => '0:10:7:0:0:0:0:0:0:3:0',
                ],
            ],
            [
                'serial_number' => 'CKJG0002',
                'device_name' => 'HQ Exit',
                'status' => Device::STATUS_APPROVED,
                'approved_at' => now()->subDays(28),
                'ip' => '192.168.1.102',
                'push_version' => '2.4.1',
                'firmware_version' => 'ZAM170-NF-Ver1.0',
                'platform' => 'ZMM220',
                'language' => '69',
                'finger_fun_on' => true,
                'face_fun_on' => true,
                'photo_fun_on' => false,
                'user_count' => 48,
                'fp_count' => 110,
                'face_count' => 38,
                'last_seen_at' => now()->subMinutes(5),
                'last_heartbeat_at' => now()->subMinutes(5),
                'last_heartbeat_source' => 'ping',
                'site_id' => $hq?->id,
                'capabilities' => [
                    'multi_bio_data_support' => HybridBio::defaultDataSupport(),
                    'multi_bio_photo_support' => HybridBio::defaultPhotoSupport(),
                ],
            ],
            [
                'serial_number' => 'CKMS0001',
                'device_name' => 'Musanze Gate',
                'status' => Device::STATUS_APPROVED,
                'approved_at' => now()->subDays(14),
                'ip' => '10.10.2.50',
                'push_version' => '2.4.2',
                'language' => '69',
                'finger_fun_on' => true,
                'face_fun_on' => false,
                'photo_fun_on' => false,
                'last_seen_at' => now()->subHour(),
                'last_heartbeat_at' => now()->subHour(),
                'last_heartbeat_source' => 'cdata',
                'site_id' => $mus?->id,
                'capabilities' => [
                    'multi_bio_data_support' => '0:1:0:0:0:0:0:0:0:0:0',
                    'multi_bio_photo_support' => '0:0:0:0:0:0:0:0:0:0:0',
                ],
            ],
            [
                'serial_number' => 'CKHY0001',
                'device_name' => 'Huye Lobby',
                'status' => Device::STATUS_APPROVED,
                'approved_at' => now()->subDays(7),
                'ip' => '10.10.3.20',
                'push_version' => '2.4.2',
                'language' => '69',
                'finger_fun_on' => true,
                'face_fun_on' => true,
                'photo_fun_on' => true,
                'last_seen_at' => now()->subMinutes(15),
                'site_id' => $huy?->id,
                'capabilities' => [
                    'multi_bio_data_support' => '0:1:0:0:0:0:0:0:0:1:1',
                    'multi_bio_photo_support' => '0:0:0:0:0:0:0:0:0:1:1',
                ],
            ],
            [
                'serial_number' => 'PENDING01',
                'device_name' => 'New Device (pending)',
                'status' => Device::STATUS_PENDING,
                'approved_at' => null,
                'ip' => '192.168.1.200',
                'push_version' => '2.4.2',
                'language' => '69',
                'last_seen_at' => now()->subMinutes(1),
                'site_id' => null,
                'capabilities' => [],
            ],
            [
                'serial_number' => 'BLOCKED01',
                'device_name' => 'Blocked Unit',
                'status' => Device::STATUS_BLOCKED,
                'rejection_reason' => 'Stolen / decommissioned',
                'approved_at' => null,
                'ip' => null,
                'push_version' => '2.2.14',
                'site_id' => $hq?->id,
                'capabilities' => [],
            ],
        ];

        foreach ($devices as $data) {
            Device::query()->updateOrCreate(
                ['serial_number' => $data['serial_number']],
                array_merge(['id' => (string) Str::uuid()], $data)
            );
        }

        $this->command?->info('Devices seeded: '.count($devices));
    }
}
