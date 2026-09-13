<?php

namespace Database\Factories;

use App\Enums\ResidentStatus;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purok = Purok::factory();

        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'sex' => fake()->randomElement(['male', 'female']),
            'birthdate' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'civil_status' => fake()->randomElement(['single', 'married', 'widowed', 'separated']),
            'contact_number' => fake()->optional()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'purok_id' => $purok,
            'household_id' => Household::factory()->state(['purok_id' => $purok]),
            'is_registered_voter' => fake()->boolean(),
            'status' => ResidentStatus::Active,
            'birthplace' => fake()->optional()->city(),
            'occupation' => fake()->optional()->jobTitle(),
            'remarks' => null,
            'created_by_id' => User::factory(),
            'updated_by_id' => null,
        ];
    }
}
