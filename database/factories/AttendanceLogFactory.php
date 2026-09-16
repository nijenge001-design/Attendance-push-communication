<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'pin' => (string) fake()->numberBetween(1000, 9999),
            'device_serial' => strtoupper(fake()->bothify('SN######')),
            'timestamp' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => fake()->randomElement([0, 1, 4, 5]), // check-in / check-out variants
            'verify_mode' => fake()->randomElement([0, 1, 3, 4, 15]), // password, FP, card, face, etc.
            'workcode' => null,
            'reserved1' => null,
            'reserved2' => null,
            'id_number' => null,
            'type' => 0,
            'mask_flag' => fake()->optional(0.2)->randomElement([0, 1]),
            'temperature' => fake()->optional(0.15)->randomFloat(2, 35.5, 37.8),
            'conv_temperature' => null,
        ];
    }

    public function forDevice(string $serial): static
    {
        return $this->state(fn () => ['device_serial' => $serial]);
    }

    public function forPin(string $pin): static
    {
        return $this->state(fn () => ['pin' => $pin]);
    }

    public function checkIn(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }

    public function checkOut(): static
    {
        return $this->state(fn () => ['status' => 1]);
    }
}
