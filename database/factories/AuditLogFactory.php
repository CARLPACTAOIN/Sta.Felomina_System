<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => 'document_submitted',
            'auditable_type' => DocumentRecord::class,
            'auditable_id' => DocumentRecord::factory(),
            'summary' => 'Document submitted for Captain approval.',
            'context' => null,
        ];
    }
}
