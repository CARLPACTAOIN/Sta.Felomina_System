<?php

namespace Database\Factories;

use App\Models\ResidentDocumentRequestCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentDocumentRequestCounter> */
class ResidentDocumentRequestCounterFactory extends Factory
{
    public function definition(): array
    {
        return ['year' => fake()->unique()->numberBetween(2020, 2100), 'last_sequence' => fake()->numberBetween(0, 500)];
    }
}
