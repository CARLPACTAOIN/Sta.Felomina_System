<?php

namespace Database\Factories;

use App\Models\Purok;
use App\Models\PurokResolutionFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurokResolutionFile>
 */
class PurokResolutionFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purok_id' => Purok::factory(),
            'path' => fake()->unique()->bothify('purok-resolutions/##########.pdf'),
            'original_name' => 'purok-resolution.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_024, 10_485_760),
            'uploaded_by_id' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
