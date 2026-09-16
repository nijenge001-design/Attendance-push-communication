<?php

namespace Database\Factories;

use App\Models\BioTemplate;
use App\Support\HybridBio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BioTemplate>
 */
class BioTemplateFactory extends Factory
{
    protected $model = BioTemplate::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            HybridBio::TYPE_FINGERPRINT,
            HybridBio::TYPE_NIR_FACE,
            HybridBio::TYPE_VISIBLE_FACE,
            HybridBio::TYPE_FINGER_VEIN,
        ]);

        return [
            'id' => (string) Str::uuid(),
            'device_serial' => strtoupper(fake()->bothify('SN######')),
            'pin' => (string) fake()->numberBetween(1000, 9999),
            'type' => $type,
            'no' => 0,
            'index' => $type === HybridBio::TYPE_FINGERPRINT ? fake()->numberBetween(0, 9) : 0,
            'valid' => 1,
            'duress' => 0,
            'major_ver' => $type === HybridBio::TYPE_VISIBLE_FACE ? '3' : '10',
            'minor_ver' => '0',
            'format' => '0',
            'template_data' => base64_encode(random_bytes(64)),
        ];
    }

    public function fingerprint(): static
    {
        return $this->state(fn () => [
            'type' => HybridBio::TYPE_FINGERPRINT,
            'index' => fake()->numberBetween(0, 9),
            'major_ver' => '10',
        ]);
    }

    public function visibleFace(): static
    {
        return $this->state(fn () => [
            'type' => HybridBio::TYPE_VISIBLE_FACE,
            'index' => 0,
            'major_ver' => '3',
        ]);
    }

    public function visiblePalm(): static
    {
        return $this->state(fn () => [
            'type' => HybridBio::TYPE_VISIBLE_PALM,
            'index' => 0,
            'major_ver' => '1',
        ]);
    }
}
