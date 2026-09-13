<?php

namespace Database\Factories;

use App\Models\Purok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purok>
 */
class PurokFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Purok '.fake()->unique()->numberBetween(1, 99),
            'code' => fake()->unique()->bothify('purok-##'),
            'sort_order' => fake()->unique()->numberBetween(1, 99),
            'is_active' => true,
        ];
    }
}
