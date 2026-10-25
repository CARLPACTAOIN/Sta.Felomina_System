<?php

namespace Database\Factories;

use App\Models\ResidentDocumentRequest;
use App\Models\ResidentDocumentRequestPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentDocumentRequestPayment> */
class ResidentDocumentRequestPaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['resident_document_request_id' => ResidentDocumentRequest::factory()->readyForPayment(), 'receipt_reference' => 'OR-'.fake()->unique()->numerify('########'), 'amount' => '50.00', 'received_by_id' => User::factory(), 'paid_at' => now()];
    }
}
