<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
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
            'address' => fake()->streetAddress(),
            'household_head_name' => fake()->name(),
            'notes' => null,
            'created_by_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}
