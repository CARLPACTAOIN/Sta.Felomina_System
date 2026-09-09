<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRecord>
 */
class DocumentRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = 2026;

        return [
            'document_type_id' => DocumentType::factory(),
            'document_category_id' => DocumentCategory::factory(),
            'created_by_id' => User::factory(),
            'document_number' => fake()->unique()->bothify('DOC-'.$year.'-###'),
            'document_year' => $year,
            'title' => fake()->sentence(5),
            'document_date' => fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'subject' => fake()->paragraph(),
            'keywords' => fake()->words(3, true),
            'status' => DocumentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Published,
        ]);
    }
}
