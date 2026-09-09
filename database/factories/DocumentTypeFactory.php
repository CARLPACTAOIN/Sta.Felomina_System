<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->unique()->word();
        $prefix = Str::upper(fake()->unique()->lexify('???'));

        return [
            'name' => Str::headline($name),
            'code' => Str::slug($name, '_'),
            'number_prefix' => $prefix,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
