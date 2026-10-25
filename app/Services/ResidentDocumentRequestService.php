<?php

namespace App\Services;

use App\Enums\ResidentDocumentRequestStatus;
use App\Enums\ResidentStatus;
use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\ResidentDocumentRequestCounter;
use App\Models\User;
use App\Notifications\ResidentDocumentRequestWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidentDocumentRequestService
{
    /** @param array{purpose:string,intake_notes?:string|null} $input */
    public function create(User $actor, Resident $resident, DocumentType $type, array $input): ResidentDocumentRequest
    {
        $this->ensureEligible($resident, $type);

        if ($actor->purok_id !== $resident->purok_id) {
            throw ValidationException::withMessages(['residentId' => 'The selected resident must belong to your assigned Purok.']);
        }

        if (ResidentDocumentRequest::query()->where('resident_id', $resident->id)->where('document_type_id', $type->id)->where('open_guard', true)->exists()) {
            throw ValidationException::withMessages(['documentTypeId' => 'An open request already exists for this resident and document type.']);
        }

        return DB::transaction(function () use ($actor, $resident, $type, $input): ResidentDocumentRequest {
            $request = ResidentDocumentRequest::create(array_merge($this->snapshotAttributes($resident, $type), [
                'reference' => $this->nextReference((int) now()->year),
                'created_by_id' => $actor->id,
                'purpose' => trim($input['purpose']),
                'intake_notes' => filled($input['intake_notes'] ?? null) ? trim((string) $input['intake_notes']) : null,
                'status' => ResidentDocumentRequestStatus::Draft,
                'open_guard' => true,
            ]));

            $this->recordHistory($request, null, ResidentDocumentRequestStatus::Draft, $actor);
            $this->recordAudit($actor, $request, 'resident_document_request_created', 'Resident document request created.');

            return $request;
        }, attempts: 3);
    }

    /** @param array{purpose:string,intake_notes?:string|null} $input */
    public function update(User $actor, ResidentDocumentRequest $request, Resident $resident, DocumentType $type, array $input): void
    {
        if (! $request->isEditableByChairperson()) {
            throw ValidationException::withMessages(['request' => 'Only Draft or Needs Information requests may be corrected.']);
        }

        $this->ensureEligible($resident, $type);

        if ($actor->purok_id !== $request->purok_id || $resident->purok_id !== $request->purok_id) {
            throw ValidationException::withMessages(['residentId' => 'The selected resident must belong to the request Purok.']);
        }

        DB::transaction(function () use ($actor, $request, $resident, $type, $input): void {
            $request->forceFill(array_merge($this->snapshotAttributes($resident, $type), [
                'purpose' => trim($input['purpose']),
                'intake_notes' => filled($input['intake_notes'] ?? null) ? trim((string) $input['intake_notes']) : null,
                'latest_reason' => null,
            ]))->save();

            $this->recordAudit($actor, $request, 'resident_document_request_corrected', 'Resident document request corrected.');
        });
    }

    public function submit(User $actor, ResidentDocumentRequest $request): void
    {
        if (! $request->canSubmit()) {
            throw ValidationException::withMessages(['request' => 'Only Draft or Needs Information requests may be submitted.']);
        }

        DB::transaction(function () use ($actor, $request): void {
            $from = $request->status;
            $request->forceFill(['status' => ResidentDocumentRequestStatus::Submitted, 'submitted_by_id' => $actor->id, 'submitted_at' => now(), 'latest_reason' => null])->save();
            $this->recordHistory($request, $from, ResidentDocumentRequestStatus::Submitted, $actor);
            $this->recordAudit($actor, $request, 'resident_document_request_submitted', 'Resident document request submitted to the Secretary.');
        });

        $this->notifySecretaries($request, 'Resident document request awaiting processing.');
    }

    public function requestInformation(User $actor, ResidentDocumentRequest $request, string $reason): void
    {
        $this->transitionWithReason($actor, $request, ResidentDocumentRequestStatus::NeedsInformation, $reason, 'resident_document_request_information_requested', 'Additional request information required.');
        $this->notifyAssignedChairpeople($request, 'Additional information is required for this resident document request.');
    }

    public function cancel(User $actor, ResidentDocumentRequest $request, string $reason): void
    {
        $this->transitionWithReason($actor, $request, ResidentDocumentRequestStatus::Cancelled, $reason, 'resident_document_request_cancelled', 'Resident document request cancelled.');

        if ($actor->can(StaffPermission::ProcessResidentDocumentRequests->value)) {
            $this->notifyAssignedChairpeople($request, 'Resident document request cancelled.');
        } else {
            $this->notifySecretaries($request, 'Resident document request cancelled by the Purok Chairperson.');
        }
    }

    public function linkDocument(User $actor, ResidentDocumentRequest $request, DocumentRecord $document): void
    {
        if (! $request->canBeProcessed()) {
            throw ValidationException::withMessages(['request' => 'Only an unlinked Submitted request may receive an official document.']);
        }

        DB::transaction(function () use ($actor, $request, $document): void {
            $request->forceFill(['document_record_id' => $document->id])->save();
            $this->recordAudit($actor, $request, 'resident_document_request_document_linked', 'Linked official document record created.', ['document_record_id' => $document->id]);
        });
    }

    public function markReadyForPayment(User $actor, ResidentDocumentRequest $request): void
    {
        if ($request->status !== ResidentDocumentRequestStatus::Submitted || $request->document_record_id === null) {
            return;
        }

        $from = $request->status;
        $request->forceFill(['status' => ResidentDocumentRequestStatus::ReadyForPayment])->save();
        $this->recordHistory($request, $from, ResidentDocumentRequestStatus::ReadyForPayment, $actor);
        $this->recordAudit($actor, $request, 'resident_document_request_document_published', 'Linked official document published; request is ready for payment.');
    }

    public function notifyReadyForPayment(ResidentDocumentRequest $request): void
    {
        $this->notifyAssignedChairpeople($request, 'Linked official document published; request is ready for payment.');
    }

    public function recordPayment(User $actor, ResidentDocumentRequest $request, string $receiptReference, string $amount): void
    {
        if (! $request->canRecordPayment()) {
            throw ValidationException::withMessages(['payment' => 'This request cannot accept a payment.']);
        }

        $expected = number_format((float) $request->expected_fee, 2, '.', '');
        $collected = number_format((float) $amount, 2, '.', '');

        if ($expected !== $collected) {
            throw ValidationException::withMessages(['amount' => 'The collected amount must exactly match the captured expected fee.']);
        }

        DB::transaction(function () use ($actor, $request, $receiptReference, $collected): void {
            $request->payment()->create(['receipt_reference' => trim($receiptReference), 'amount' => $collected, 'received_by_id' => $actor->id, 'paid_at' => now()]);
            $this->recordAudit($actor, $request, 'resident_document_request_paid', 'Cash payment recorded.', ['receipt_reference' => trim($receiptReference), 'amount' => $collected]);
        });
    }

    public function release(User $actor, ResidentDocumentRequest $request, string $recipientName): void
    {
        if (! $request->canRelease()) {
            throw ValidationException::withMessages(['release' => 'Payment must be recorded before this request can be released.']);
        }

        DB::transaction(function () use ($actor, $request, $recipientName): void {
            $from = $request->status;
            $request->forceFill(['status' => ResidentDocumentRequestStatus::Released, 'open_guard' => null, 'released_at' => now(), 'released_by_id' => $actor->id, 'released_to_name' => trim($recipientName)])->save();
            $this->recordHistory($request, $from, ResidentDocumentRequestStatus::Released, $actor);
            $this->recordAudit($actor, $request, 'resident_document_request_released', 'Official document released to recipient.', ['released_to_name' => trim($recipientName)]);
        });

        $this->notifyAssignedChairpeople($request, 'Resident document request released.');
    }

    private function transitionWithReason(User $actor, ResidentDocumentRequest $request, ResidentDocumentRequestStatus $to, string $reason, string $action, string $summary): void
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        DB::transaction(function () use ($actor, $request, $to, $trimmedReason, $action, $summary): void {
            $from = $request->status;
            $request->forceFill(['status' => $to, 'open_guard' => $to->isOpen() ? true : null, 'latest_reason' => $trimmedReason])->save();
            $this->recordHistory($request, $from, $to, $actor, $trimmedReason);
            $this->recordAudit($actor, $request, $action, $summary, ['reason' => $trimmedReason]);
        });
    }

    private function ensureEligible(Resident $resident, DocumentType $type): void
    {
        if ($resident->status !== ResidentStatus::Active) {
            throw ValidationException::withMessages(['residentId' => 'Only Active residents may receive a new document request.']);
        }

        if (! $type->is_active || ! $type->is_requestable || ! $type->requests_enabled || $type->standard_fee === null) {
            throw ValidationException::withMessages(['documentTypeId' => 'The selected document type is not enabled for resident requests.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshotAttributes(Resident $resident, DocumentType $type): array
    {
        $resident->loadMissing(['household', 'purok']);

        return [
            'resident_id' => $resident->id, 'document_type_id' => $type->id, 'purok_id' => $resident->purok_id,
            'resident_name_snapshot' => $resident->fullName(), 'address_snapshot' => $resident->address,
            'household_code_snapshot' => $resident->household->household_code, 'household_head_snapshot' => $resident->household->household_head_name,
            'purok_name_snapshot' => $resident->purok->name, 'expected_fee' => $type->standard_fee,
        ];
    }

    private function nextReference(int $year): string
    {
        $counter = ResidentDocumentRequestCounter::query()->where('year', $year)->lockForUpdate()->first();
        if ($counter === null) {
            $counter = ResidentDocumentRequestCounter::create(['year' => $year, 'last_sequence' => 0]);
            $counter->refresh();
        }

        $counter->increment('last_sequence');

        return sprintf('REQ-%d-%06d', $year, $counter->last_sequence);
    }

    private function recordHistory(ResidentDocumentRequest $request, ?ResidentDocumentRequestStatus $from, ResidentDocumentRequestStatus $to, User $actor, ?string $reason = null): void
    {
        $request->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'reason' => $reason]);
    }

    /** @param array<string, mixed> $context */
    private function recordAudit(User $actor, ResidentDocumentRequest $request, string $action, string $summary, array $context = []): void
    {
        AuditLog::record($actor, $action, $request, $summary, $context);
    }

    private function notifySecretaries(ResidentDocumentRequest $request, string $message): void
    {
        User::role(UserRole::Secretary->value)->where('is_active', true)->each(fn (User $user) => $user->notify($this->notification($request, $message)));
    }

    private function notifyAssignedChairpeople(ResidentDocumentRequest $request, string $message): void
    {
        User::role(UserRole::PurokChairperson->value)->where('is_active', true)->where('purok_id', $request->purok_id)->each(fn (User $user) => $user->notify($this->notification($request, $message)));
    }

    private function notification(ResidentDocumentRequest $request, string $message): ResidentDocumentRequestWorkflowNotification
    {
        return new ResidentDocumentRequestWorkflowNotification($request->id, $request->reference, $message, route('resident-document-requests.show', $request, absolute: false));
    }
}
