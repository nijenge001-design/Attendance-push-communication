<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        $city = fake()->randomElement(['Kigali', 'Musanze', 'Huye', 'Rubavu', 'Muhanga', 'Rwamagana']);

        return [
            'id' => (string) Str::uuid(),
            'code' => strtoupper(fake()->unique()->bothify('SITE-###??')),
            'name' => fake()->company().' '.$city,
            'address' => fake()->streetAddress(),
            'city' => $city,
            'country' => 'Rwanda',
            'timezone' => 'Africa/Kigali',
            'is_active' => true,
            'meta' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
