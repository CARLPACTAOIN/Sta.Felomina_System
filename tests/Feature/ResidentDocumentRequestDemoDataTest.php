<?php

use App\Enums\ResidentDocumentRequestStatus;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use Database\Seeders\DemoDataSeeder;

test('demo data includes each resident document request lifecycle state', function () {
    $this->seed(DemoDataSeeder::class);

    $statuses = ResidentDocumentRequest::query()->pluck('status')->map(
        fn (ResidentDocumentRequestStatus|string $status): string => $status instanceof ResidentDocumentRequestStatus ? $status->value : $status
    );

    expect($statuses)
        ->toContain(ResidentDocumentRequestStatus::Draft->value)
        ->toContain(ResidentDocumentRequestStatus::Submitted->value)
        ->toContain(ResidentDocumentRequestStatus::NeedsInformation->value)
        ->toContain(ResidentDocumentRequestStatus::ReadyForPayment->value)
        ->toContain(ResidentDocumentRequestStatus::Released->value)
        ->toContain(ResidentDocumentRequestStatus::Cancelled->value);

    $readyRequest = ResidentDocumentRequest::query()->where('status', ResidentDocumentRequestStatus::ReadyForPayment)->firstOrFail();
    $releasedRequest = ResidentDocumentRequest::query()->where('status', ResidentDocumentRequestStatus::Released)->firstOrFail();
    $resident = Resident::query()->where('resident_code', 'RES-P1-001')->sole();

    expect($readyRequest->document_record_id)->not->toBeNull()
        ->and($readyRequest->payment)->not->toBeNull()
        ->and($releasedRequest->document_record_id)->not->toBeNull()
        ->and($releasedRequest->expected_fee)->toBe('0.00')
        ->and($releasedRequest->released_at)->not->toBeNull()
        ->and($resident->normalized_full_name)->toBe('juanito mendoza dela cruz');
});
