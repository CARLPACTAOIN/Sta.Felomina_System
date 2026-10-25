<?php

namespace Database\Factories;

use App\Enums\ResidentDocumentRequestStatus;
use App\Models\DocumentType;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentDocumentRequest> */
class ResidentDocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'REQ-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'resident_id' => Resident::factory(), 'document_type_id' => DocumentType::factory()->requestEnabled(), 'purok_id' => Purok::factory(),
            'created_by_id' => User::factory()->purokChairperson(), 'resident_name_snapshot' => fake()->name(), 'address_snapshot' => fake()->address(),
            'household_code_snapshot' => 'HH-'.fake()->numerify('####'), 'household_head_snapshot' => fake()->name(), 'purok_name_snapshot' => fake()->word(),
            'purpose' => fake()->sentence(), 'expected_fee' => '50.00', 'status' => ResidentDocumentRequestStatus::Draft, 'open_guard' => true,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => ResidentDocumentRequestStatus::Submitted, 'submitted_at' => now(), 'open_guard' => true]);
    }

    public function readyForPayment(string $fee = '50.00'): static
    {
        return $this->state(fn (): array => ['status' => ResidentDocumentRequestStatus::ReadyForPayment, 'expected_fee' => $fee, 'open_guard' => true]);
    }

    public function released(): static
    {
        return $this->state(fn (): array => ['status' => ResidentDocumentRequestStatus::Released, 'open_guard' => null, 'released_at' => now(), 'released_by_id' => User::factory(), 'released_to_name' => fake()->name()]);
    }
}
