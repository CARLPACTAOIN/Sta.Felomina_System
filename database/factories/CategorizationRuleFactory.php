<?php

namespace Database\Factories;

use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorizationRule>
 */
class CategorizationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_category_id' => DocumentCategory::factory(),
            'document_type_id' => null,
            'name' => fake()->unique()->words(3, true),
            'keywords' => fake()->unique()->words(4),
            'priority' => fake()->numberBetween(10, 200),
            'is_active' => true,
        ];
    }

    public function forDocumentType(DocumentType $documentType): static
    {
        return $this->state(fn (array $attributes): array => [
            'document_type_id' => $documentType->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
