<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentDocumentRequestStatus;
use App\Enums\ResidentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use App\Services\ResidentDocumentRequestService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function requestContext(string $fee = '75.00'): array
{
    $purok = Purok::factory()->create();
    $chairperson = User::factory()->purokChairperson()->create(['purok_id' => $purok->id]);
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    $resident = Resident::factory()->create(['purok_id' => $purok->id, 'household_id' => $household->id, 'status' => ResidentStatus::Active]);
    $type = DocumentType::factory()->requestEnabled($fee)->create();

    return compact('purok', 'chairperson', 'household', 'resident', 'type');
}

function createResidentRequest(array $context, string $fee = '75.00'): ResidentDocumentRequest
{
    return app(ResidentDocumentRequestService::class)->create($context['chairperson'], $context['resident'], $context['type'], ['purpose' => 'Employment requirement', 'intake_notes' => 'Presented valid ID.']);
}

test('chairperson creates an assigned Purok draft with immutable reference and snapshots', function () {
    $context = requestContext();
    $request = createResidentRequest($context);

    expect($request->reference)->toMatch('/^REQ-'.now()->year.'-\d{6}$/')
        ->and($request->status)->toBe(ResidentDocumentRequestStatus::Draft)
        ->and($request->expected_fee)->toBe('75.00')
        ->and($request->resident_name_snapshot)->toBe($context['resident']->fullName())
        ->and($request->statusHistories()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', ['action' => 'resident_document_request_created', 'auditable_id' => $request->id]);
});

test('only active residents and enabled document types can receive new requests', function () {
    $context = requestContext();
    $context['resident']->update(['status' => ResidentStatus::Inactive]);

    expect(fn () => createResidentRequest($context))->toThrow(ValidationException::class);

    $context['resident']->update(['status' => ResidentStatus::Active]);
    $context['type']->update(['requests_enabled' => false]);

    expect(fn () => createResidentRequest($context))->toThrow(ValidationException::class);
});

test('open duplicate resident document requests are blocked and cancellation permits a replacement', function () {
    $context = requestContext();
    $request = createResidentRequest($context);
    $service = app(ResidentDocumentRequestService::class);

    expect(fn () => createResidentRequest($context))->toThrow(ValidationException::class);

    $service->cancel($context['chairperson'], $request, 'Resident withdrew the initial request.');
    $replacement = createResidentRequest($context);

    expect($replacement->id)->not->toBe($request->id)
        ->and($replacement->status)->toBe(ResidentDocumentRequestStatus::Draft);
});

test('request policy follows the request Purok after chairperson reassignment', function () {
    $context = requestContext();
    $request = createResidentRequest($context);
    $otherPurok = Purok::factory()->create();
    $context['chairperson']->update(['purok_id' => $otherPurok->id]);

    expect(Gate::forUser($context['chairperson'])->allows('view', $request))->toBeFalse();

    $this->actingAs($context['chairperson'])
        ->get(route('resident-document-requests.show', $request))
        ->assertNotFound();
});

test('role boundaries expose the correct request workspace routes', function () {
    $context = requestContext();
    $request = createResidentRequest($context);
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->secretary()->create();

    $this->actingAs($context['chairperson'])->get(route('resident-document-requests.index'))->assertSuccessful();
    $this->actingAs($context['chairperson'])->get(route('resident-document-requests.create'))->assertSuccessful();
    $this->actingAs($secretary)->get(route('resident-document-requests.create'))->assertForbidden();
    $this->actingAs($captain)->get(route('requestable-document-types.index'))->assertSuccessful();
    $this->actingAs($secretary)->get(route('requestable-document-types.index'))->assertForbidden();
    expect(Gate::forUser($secretary)->allows('view', $request))->toBeTrue();
    $this->actingAs($secretary)->get(route('resident-document-requests.show', $request))->assertSuccessful();
});

test('secretary can request information and chairperson can correct and resubmit', function () {
    $context = requestContext();
    $request = createResidentRequest($context);
    $secretary = User::factory()->secretary()->create();
    $service = app(ResidentDocumentRequestService::class);

    $service->submit($context['chairperson'], $request);
    $service->requestInformation($secretary, $request, 'Please specify the employer name.');
    $request->refresh();

    expect($request->status)->toBe(ResidentDocumentRequestStatus::NeedsInformation)
        ->and($context['chairperson']->notifications()->count())->toBe(1);

    $service->update($context['chairperson'], $request, $context['resident'], $context['type'], ['purpose' => 'Employment requirement for Sta. Felomina Cooperative', 'intake_notes' => null]);
    $service->submit($context['chairperson'], $request);

    expect($request->refresh()->status)->toBe(ResidentDocumentRequestStatus::Submitted)
        ->and($secretary->notifications()->count())->toBe(2);
});

test('linked document publication makes the request ready for payment without changing QR identity', function () {
    Storage::fake('local');
    $context = requestContext();
    $request = createResidentRequest($context);
    $secretary = User::factory()->secretary()->create();
    $captain = User::factory()->captain()->create();
    $service = app(ResidentDocumentRequestService::class);
    $service->submit($context['chairperson'], $request);
    Storage::disk('local')->put('document-drafts/request.pdf', '%PDF request');
    $document = DocumentRecord::factory()->pendingApproval()->withDraftPdf('document-drafts/request.pdf')->create(['document_type_id' => $context['type']->id, 'document_category_id' => DocumentCategory::factory(), 'created_by_id' => $secretary->id, 'submitted_by_id' => $secretary->id]);
    $token = $document->verification_token;
    $service->linkDocument($secretary, $request, $document);

    $document->approveForPublication($captain);

    expect($request->refresh()->status)->toBe(ResidentDocumentRequestStatus::ReadyForPayment)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Published)
        ->and($document->verification_token)->toBe($token);
});

test('payment must equal captured fee and release is separate and immutable', function () {
    $context = requestContext('100.00');
    $request = ResidentDocumentRequest::factory()->readyForPayment('100.00')->create(array_merge(['resident_id' => $context['resident']->id, 'document_type_id' => $context['type']->id, 'purok_id' => $context['purok']->id, 'created_by_id' => $context['chairperson']->id], ['reference' => 'REQ-'.now()->year.'-999901']));
    $secretary = User::factory()->secretary()->create();
    $service = app(ResidentDocumentRequestService::class);

    expect(fn () => $service->recordPayment($secretary, $request, 'OR-100', '99.00'))->toThrow(ValidationException::class);
    $service->recordPayment($secretary, $request, 'OR-100', '100.00');
    $service->release($secretary, $request->refresh(), 'Maria Santos');

    expect($request->refresh()->status)->toBe(ResidentDocumentRequestStatus::Released)
        ->and($request->released_to_name)->toBe('Maria Santos')
        ->and($request->payment?->receipt_reference)->toBe('OR-100');
});

test('zero fee request may be released without a payment', function () {
    $context = requestContext('0.00');
    $request = ResidentDocumentRequest::factory()->readyForPayment('0.00')->create(['resident_id' => $context['resident']->id, 'document_type_id' => $context['type']->id, 'purok_id' => $context['purok']->id, 'created_by_id' => $context['chairperson']->id, 'reference' => 'REQ-'.now()->year.'-999902']);
    $secretary = User::factory()->secretary()->create();

    app(ResidentDocumentRequestService::class)->release($secretary, $request, 'Juan dela Cruz');

    expect($request->refresh()->status)->toBe(ResidentDocumentRequestStatus::Released)
        ->and($request->payment)->toBeNull();
});
