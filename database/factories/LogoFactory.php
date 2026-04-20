<?php

namespace Database\Factories;

use App\Models\Logo;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logo>
 */
class LogoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'object_key' => $this->faker->slug().'.png',
            'url' => $this->faker->imageUrl(),
            'mime_type' => 'image/png',
            'size_bytes' => $this->faker->numberBetween(1024, 1048576),
        ];
    }
}
