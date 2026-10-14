<?php

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\DocumentRecord;
use App\Models\User;

class DocumentRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(StaffPermission::ViewDocuments->value);
    }

    public function view(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::ViewDocuments->value);
    }

    public function create(User $user): bool
    {
        return $user->can(StaffPermission::CreateDocuments->value);
    }

    public function updateMetadata(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::UpdateDocuments->value)
            && $documentRecord->canEditMetadata();
    }

    public function uploadDraftPdf(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::UploadDocumentDraftPdf->value)
            && $documentRecord->canReplaceDraftPdf();
    }

    public function downloadDraftPdf(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::DownloadDocumentDraftPdf->value);
    }

    public function downloadQrCode(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::DownloadDocumentQr->value);
    }

    public function review(User $user): bool
    {
        return $user->can(StaffPermission::ReviewDocuments->value);
    }

    public function submit(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::SubmitDocuments->value)
            && $documentRecord->canSubmitForApproval();
    }

    public function returnForRevision(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::ReturnDocuments->value)
            && $documentRecord->canReturnForRevision();
    }

    public function approve(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::ApproveDocuments->value)
            && $documentRecord->canApproveForPublication();
    }

    public function archive(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::ArchiveDocuments->value)
            && $documentRecord->canArchive();
    }

    public function revoke(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::RevokeDocuments->value)
            && $documentRecord->canRevoke();
    }

    public function supersede(User $user, DocumentRecord $documentRecord): bool
    {
        return $user->can(StaffPermission::SupersedeDocuments->value)
            && $documentRecord->canSupersede();
    }
}
