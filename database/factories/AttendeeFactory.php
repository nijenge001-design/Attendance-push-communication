<?php

namespace Database\Factories;

use App\Models\Attendee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    protected $model = Attendee::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'pin' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->name(),
            'privilege' => 0,
            'password' => null,
            'card_number' => fake()->optional(0.4)->numerify('##########'),
            'vice_card' => null,
            'group_id' => 1,
            'timezone' => '0000000000000000',
            'verification_mode' => -1,
        ];
    }

    public function adminPrivilege(): static
    {
        return $this->state(fn () => ['privilege' => 14]);
    }

    public function withCard(): static
    {
        return $this->state(fn () => [
            'card_number' => fake()->unique()->numerify('##########'),
        ]);
    }
}
