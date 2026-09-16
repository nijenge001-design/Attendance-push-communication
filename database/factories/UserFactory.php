<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => $this->uniqueRwandanPhone(),
            'password' => static::$password ??= Hash::make('password'),
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'role' => UserRole::Viewer,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Generate a unique Rwanda-style mobile number (unique within the process).
     */
    protected function uniqueRwandanPhone(): string
    {
        // 078 / 079 / 072 / 073 style local numbers
        $prefix = fake()->randomElement(['078', '079', '072', '073']);

        return fake()->unique()->numerify($prefix.'#######');
    }

    /**
     * Indicate that the model's email / phone are unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Manager,
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Operator,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Viewer,
        ]);
    }

    public function integration(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Integration,
        ]);
    }

    /**
     * User without email (email is nullable in migration).
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
        ]);
    }
}
