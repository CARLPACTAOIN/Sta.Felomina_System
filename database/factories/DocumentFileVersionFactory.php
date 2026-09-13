<?php

namespace Database\Factories;

use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFileVersion>
 */
class DocumentFileVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_record_id' => DocumentRecord::factory(),
            'version_number' => 1,
            'file_path' => 'document-officials/1/versions/1.pdf',
            'original_name' => 'official.pdf',
            'mime_type' => 'application/pdf',
            'size' => 256,
            'is_current' => true,
            'approved_at' => now(),
            'approved_by_id' => User::factory()->captain(),
        ];
    }
}
