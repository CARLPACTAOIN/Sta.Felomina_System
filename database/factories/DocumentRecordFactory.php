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
            'suggested_document_category_id' => null,
            'category_suggestion_rule_id' => null,
            'category_suggestion_terms' => null,
            'category_suggestion_reason' => null,
            'category_suggested_at' => null,
            'category_overridden_at' => null,
            'category_overridden_by_id' => null,
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

    public function archived(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'status' => DocumentStatus::Archived,
            'archived_at' => now(),
            'archived_by_id' => User::factory()->captain(),
            'archive_reason' => 'Historical retention only.',
        ]);
    }

    public function revoked(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'status' => DocumentStatus::Revoked,
            'revoked_at' => now(),
            'revoked_by_id' => User::factory()->captain(),
            'revoke_reason' => 'Issued in error.',
        ]);
    }

    public function superseded(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'status' => DocumentStatus::Superseded,
            'superseded_at' => now(),
            'superseded_by_id' => User::factory()->captain(),
            'supersede_reason' => 'Replaced by a newer official document.',
            'supersede_reference' => 'Replacement reference pending.',
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::PendingApproval,
            'submitted_at' => now(),
            'submitted_by_id' => User::factory(),
        ]);
    }

    public function needsRevision(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::NeedsRevision,
            'submitted_at' => now()->subHour(),
            'submitted_by_id' => User::factory(),
            'return_comment' => 'Please correct the prepared PDF.',
            'returned_at' => now(),
            'returned_by_id' => User::factory()->captain(),
        ]);
    }

    public function withDraftPdf(string $path = 'document-drafts/test.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'draft_pdf_path' => $path,
            'draft_pdf_original_name' => basename($path),
            'draft_pdf_mime_type' => 'application/pdf',
            'draft_pdf_size' => 256,
            'draft_pdf_uploaded_at' => now(),
            'draft_pdf_uploaded_by_id' => User::factory(),
        ]);
    }
}
