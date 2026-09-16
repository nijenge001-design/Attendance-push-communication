<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'serial_number' => strtoupper(fake()->bothify('SN######??')),
            'device_name' => fake()->optional()->words(2, true),
            'status' => Device::STATUS_PENDING,
            'approved_at' => null,
            'rejection_reason' => null,
            'ip' => fake()->optional()->ipv4(),
            'mac_address' => null,
            'firmware_version' => null,
            'push_version' => '2.4.2',
            'platform' => null,
            'language' => '69',
            'user_count' => 0,
            'fp_count' => 0,
            'face_count' => 0,
            'attlog_count' => 0,
            'finger_fun_on' => true,
            'face_fun_on' => true,
            'photo_fun_on' => true,
            'last_seen_at' => null,
            'last_heartbeat_at' => null,
            'last_heartbeat_source' => null,
            'capabilities' => [],
            'site_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Device::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function blocked(?string $reason = 'blocked by test'): static
    {
        return $this->state(fn () => [
            'status' => Device::STATUS_BLOCKED,
            'rejection_reason' => $reason,
        ]);
    }

    public function withHybridBio(
        string $dataSupport = '0:1:0:0:0:0:0:0:0:1:1',
        string $photoSupport = '0:0:0:0:0:0:0:0:0:1:0'
    ): static {
        return $this->state(fn () => [
            'capabilities' => [
                'multi_bio_data_support' => $dataSupport,
                'multi_bio_photo_support' => $photoSupport,
                'options_pushed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
