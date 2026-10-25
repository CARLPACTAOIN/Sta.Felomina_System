<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\ResidentDocumentRequest;
use App\Models\User;

class ResidentDocumentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewResidentDocumentRequestsBarangay->value)
            || $user->can(StaffPermission::ViewResidentDocumentRequestsAssigned->value);
    }

    public function view(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::ViewResidentDocumentRequestsBarangay->value)
            || ($user->can(StaffPermission::ViewResidentDocumentRequestsAssigned->value) && $user->purok_id === $request->purok_id);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::ManageResidentDocumentRequestsAssigned->value) && $user->purok_id !== null;
    }

    public function update(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::ManageResidentDocumentRequestsAssigned->value)
            && $user->purok_id === $request->purok_id && $request->isEditableByChairperson();
    }

    public function submit(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::ManageResidentDocumentRequestsAssigned->value)
            && $user->purok_id === $request->purok_id && $request->canSubmit();
    }

    public function cancel(User $user, ResidentDocumentRequest $request): bool
    {
        return ($user->can(StaffPermission::ManageResidentDocumentRequestsAssigned->value)
                && $user->purok_id === $request->purok_id && $request->canCancelByChairperson())
            || ($user->can(StaffPermission::ProcessResidentDocumentRequests->value) && $request->canBeProcessed());
    }

    public function process(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::ProcessResidentDocumentRequests->value) && $request->canBeProcessed();
    }

    public function recordPayment(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::RecordResidentDocumentRequestPayments->value) && $request->canRecordPayment();
    }

    public function release(User $user, ResidentDocumentRequest $request): bool
    {
        return $user->can(StaffPermission::ReleaseResidentDocumentRequests->value) && $request->canRelease();
    }
}
