<?php

namespace Database\Factories;

use App\Enums\ResidentDocumentRequestStatus;
use App\Models\ResidentDocumentRequest;
use App\Models\ResidentDocumentRequestStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentDocumentRequestStatusHistory> */
class ResidentDocumentRequestStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return ['resident_document_request_id' => ResidentDocumentRequest::factory(), 'from_status' => ResidentDocumentRequestStatus::Draft, 'to_status' => ResidentDocumentRequestStatus::Submitted, 'actor_id' => User::factory()];
    }
}
