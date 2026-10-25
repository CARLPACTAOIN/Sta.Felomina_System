<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Notifications\DocumentWorkflowNotification;
use App\Services\ResidentDocumentRequestService;
use Database\Factories\DocumentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @property int $id
 * @property int $document_type_id
 * @property int $document_category_id
 * @property int|null $suggested_document_category_id
 * @property int|null $category_suggestion_rule_id
 * @property int $created_by_id
 * @property string $document_number
 * @property int $document_year
 * @property string $title
 * @property Carbon $document_date
 * @property string $subject
 * @property string|null $keywords
 * @property bool $is_project_related
 * @property string|null $project_name
 * @property string|null $project_location
 * @property string|null $approved_budget
 * @property Carbon|null $project_start_date
 * @property Carbon|null $target_completion_date
 * @property string|null $project_remarks
 * @property array<int, string>|null $category_suggestion_terms
 * @property string|null $category_suggestion_reason
 * @property Carbon|null $category_suggested_at
 * @property Carbon|null $category_overridden_at
 * @property int|null $category_overridden_by_id
 * @property DocumentStatus $status
 * @property string $verification_token
 * @property string|null $draft_pdf_path
 * @property string|null $draft_pdf_original_name
 * @property string|null $draft_pdf_mime_type
 * @property int|null $draft_pdf_size
 * @property Carbon|null $draft_pdf_uploaded_at
 * @property int|null $draft_pdf_uploaded_by_id
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by_id
 * @property Carbon|null $approved_at
 * @property int|null $approved_by_id
 * @property string|null $return_comment
 * @property Carbon|null $returned_at
 * @property int|null $returned_by_id
 * @property Carbon|null $archived_at
 * @property int|null $archived_by_id
 * @property string|null $archive_reason
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_id
 * @property string|null $revoke_reason
 * @property Carbon|null $superseded_at
 * @property int|null $superseded_by_id
 * @property string|null $supersede_reason
 * @property int|null $superseded_by_document_record_id
 * @property string|null $supersede_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DocumentType $type
 * @property-read DocumentCategory $category
 * @property-read DocumentCategory|null $suggestedCategory
 * @property-read CategorizationRule|null $categorySuggestionRule
 * @property-read User $creator
 * @property-read User|null $categoryOverrider
 * @property-read User|null $draftPdfUploader
 * @property-read User|null $submitter
 * @property-read User|null $approver
 * @property-read User|null $returner
 * @property-read User|null $archiver
 * @property-read User|null $revoker
 * @property-read User|null $superseder
 * @property-read DocumentRecord|null $supersededByDocumentRecord
 */
#[Fillable([
    'document_type_id',
    'document_category_id',
    'suggested_document_category_id',
    'category_suggestion_rule_id',
    'created_by_id',
    'document_number',
    'document_year',
    'title',
    'document_date',
    'subject',
    'keywords',
    'is_project_related',
    'project_name',
    'project_location',
    'approved_budget',
    'project_start_date',
    'target_completion_date',
    'project_remarks',
    'category_suggestion_terms',
    'category_suggestion_reason',
    'category_suggested_at',
    'category_overridden_at',
    'category_overridden_by_id',
    'status',
    'verification_token',
    'draft_pdf_path',
    'draft_pdf_original_name',
    'draft_pdf_mime_type',
    'draft_pdf_size',
    'draft_pdf_uploaded_at',
    'draft_pdf_uploaded_by_id',
    'submitted_at',
    'submitted_by_id',
    'approved_at',
    'approved_by_id',
    'return_comment',
    'returned_at',
    'returned_by_id',
    'archived_at',
    'archived_by_id',
    'archive_reason',
    'revoked_at',
    'revoked_by_id',
    'revoke_reason',
    'superseded_at',
    'superseded_by_id',
    'supersede_reason',
    'superseded_by_document_record_id',
    'supersede_reference',
])]
class DocumentRecord extends Model
{
    /** @use HasFactory<DocumentRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'suggested_document_category_id');
    }

    /**
     * @return BelongsTo<CategorizationRule, $this>
     */
    public function categorySuggestionRule(): BelongsTo
    {
        return $this->belongsTo(CategorizationRule::class, 'category_suggestion_rule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function draftPdfUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_pdf_uploaded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function categoryOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'category_overridden_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function superseder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by_id');
    }

    /**
     * @return BelongsTo<DocumentRecord, $this>
     */
    public function supersededByDocumentRecord(): BelongsTo
    {
        return $this->belongsTo(DocumentRecord::class, 'superseded_by_document_record_id');
    }

    /**
     * @return HasMany<DocumentFileVersion, $this>
     */
    public function fileVersions(): HasMany
    {
        return $this->hasMany(DocumentFileVersion::class);
    }

    /**
     * @return HasOne<ResidentDocumentRequest, $this>
     */
    public function residentDocumentRequest(): HasOne
    {
        return $this->hasOne(ResidentDocumentRequest::class);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function verificationUrl(): string
    {
        return route('verification.show', $this->verification_token);
    }

    public function publicDocumentUrl(): string
    {
        return route('verification.document', $this->verification_token);
    }

    public function hasDraftPdf(): bool
    {
        return filled($this->draft_pdf_path);
    }

    public function canReplaceDraftPdf(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::NeedsRevision], true);
    }

    public function canEditMetadata(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::NeedsRevision], true);
    }

    public function canSubmitForApproval(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::NeedsRevision], true)
            && $this->hasDraftPdf();
    }

    public function canReturnForRevision(): bool
    {
        return $this->status === DocumentStatus::PendingApproval;
    }

    public function canApproveForPublication(): bool
    {
        return $this->status === DocumentStatus::PendingApproval && $this->hasDraftPdf();
    }

    public function canArchive(): bool
    {
        return $this->status === DocumentStatus::Published;
    }

    public function canRevoke(): bool
    {
        return $this->status === DocumentStatus::Published;
    }

    public function canSupersede(): bool
    {
        return $this->status === DocumentStatus::Published;
    }

    public function hasPublicPdfAccess(): bool
    {
        return in_array($this->status, [DocumentStatus::Published, DocumentStatus::Archived], true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $auditContext
     */
    public function updateMetadata(User $actor, array $metadata, array $auditContext = []): void
    {
        if (! $this->canEditMetadata()) {
            throw ValidationException::withMessages([
                'metadata' => 'Only Draft or Needs Revision records may have metadata edited.',
            ]);
        }

        $verificationToken = $this->verification_token;

        DB::transaction(function () use ($actor, $metadata, $auditContext, $verificationToken): void {
            $this->forceFill($metadata);
            $this->verification_token = $verificationToken;

            $changedFields = array_keys($this->getDirty());
            $this->save();

            $this->recordAudit($actor, 'document_metadata_updated', 'Document metadata edited.', array_merge([
                'changed_fields' => array_values(array_diff($changedFields, ['updated_at'])),
            ], $auditContext));
        });
    }

    public function submitForApproval(User $actor): void
    {
        if (! $this->canSubmitForApproval()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Draft or Needs Revision records with an uploaded Draft PDF may be submitted.',
            ]);
        }

        DB::transaction(function () use ($actor): void {
            $this->forceFill([
                'status' => DocumentStatus::PendingApproval,
                'submitted_at' => now(),
                'submitted_by_id' => $actor->id,
                'return_comment' => null,
                'returned_at' => null,
                'returned_by_id' => null,
            ])->save();

            $this->recordAudit($actor, 'document_submitted', 'Document submitted for Captain approval.');
        });

        $this->notifyCaptains('Document awaiting approval.');
    }

    public function returnForRevision(User $actor, string $comment): void
    {
        if (! $this->canReturnForRevision()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Pending Approval records may be returned for revision.',
            ]);
        }

        $trimmedComment = trim($comment);

        if ($trimmedComment === '') {
            throw ValidationException::withMessages([
                'returnComment' => 'The return comment field is required.',
            ]);
        }

        DB::transaction(function () use ($actor, $trimmedComment): void {
            $this->forceFill([
                'status' => DocumentStatus::NeedsRevision,
                'return_comment' => $trimmedComment,
                'returned_at' => now(),
                'returned_by_id' => $actor->id,
            ])->save();

            $this->recordAudit($actor, 'document_returned', 'Document returned for revision.', [
                'comment' => $trimmedComment,
            ]);
        });

        $this->workflowSubmitter()->notify($this->notification('Document returned for revision.'));
    }

    public function approveForPublication(User $actor): DocumentFileVersion
    {
        if (! $this->canApproveForPublication()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Pending Approval records with an uploaded Draft PDF may be approved.',
            ]);
        }

        $draftPdfPath = $this->draft_pdf_path;

        if (! is_string($draftPdfPath) || ! Storage::exists($draftPdfPath)) {
            throw ValidationException::withMessages([
                'workflow' => 'The uploaded Draft PDF file could not be found.',
            ]);
        }

        $approvedAt = now();
        $versionNumber = ((int) $this->fileVersions()->max('version_number')) + 1;
        $approvedPath = sprintf('document-officials/%d/versions/%d.pdf', $this->id, $versionNumber);

        if (! Storage::copy($draftPdfPath, $approvedPath)) {
            throw new RuntimeException('Unable to preserve the approved PDF version.');
        }

        $linkedRequest = null;

        try {
            $fileVersion = DB::transaction(function () use ($actor, $approvedAt, $versionNumber, $approvedPath, &$linkedRequest): DocumentFileVersion {
                $this->fileVersions()->update(['is_current' => false]);

                $fileVersion = $this->fileVersions()->create([
                    'version_number' => $versionNumber,
                    'file_path' => $approvedPath,
                    'original_name' => $this->draft_pdf_original_name ?? $this->document_number.'.pdf',
                    'mime_type' => $this->draft_pdf_mime_type ?? 'application/pdf',
                    'size' => $this->draft_pdf_size ?? Storage::size($approvedPath),
                    'is_current' => true,
                    'approved_at' => $approvedAt,
                    'approved_by_id' => $actor->id,
                ]);

                $this->forceFill([
                    'status' => DocumentStatus::Published,
                    'approved_at' => $approvedAt,
                    'approved_by_id' => $actor->id,
                ])->save();

                $this->recordAudit($actor, 'document_approved', 'Document approved and published.', [
                    'file_version_id' => $fileVersion->id,
                    'version_number' => $versionNumber,
                ]);

                $linkedRequest = $this->residentDocumentRequest()->lockForUpdate()->first();
                if ($linkedRequest instanceof ResidentDocumentRequest) {
                    app(ResidentDocumentRequestService::class)->markReadyForPayment($actor, $linkedRequest);
                }

                return $fileVersion;
            });
        } catch (Throwable $exception) {
            Storage::delete($approvedPath);

            throw $exception;
        }

        $this->workflowSubmitter()->notify($this->notification('Document approved and published.'));

        if ($linkedRequest instanceof ResidentDocumentRequest) {
            $linkedRequest->refresh();
            app(ResidentDocumentRequestService::class)->notifyReadyForPayment($linkedRequest);
        }

        return $fileVersion;
    }

    public function archive(User $actor, string $reason): void
    {
        if (! $this->canArchive()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Published records may be archived.',
            ]);
        }

        $trimmedReason = $this->validatedLifecycleReason($reason, 'archiveReason');

        DB::transaction(function () use ($actor, $trimmedReason): void {
            $this->forceFill([
                'status' => DocumentStatus::Archived,
                'archived_at' => now(),
                'archived_by_id' => $actor->id,
                'archive_reason' => $trimmedReason,
            ])->save();

            $this->recordAudit($actor, 'document_archived', 'Document archived.', [
                'reason' => $trimmedReason,
            ]);
        });
    }

    public function revoke(User $actor, string $reason): void
    {
        if (! $this->canRevoke()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Published records may be revoked.',
            ]);
        }

        $trimmedReason = $this->validatedLifecycleReason($reason, 'revokeReason');

        DB::transaction(function () use ($actor, $trimmedReason): void {
            $this->forceFill([
                'status' => DocumentStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by_id' => $actor->id,
                'revoke_reason' => $trimmedReason,
            ])->save();

            $this->recordAudit($actor, 'document_revoked', 'Document revoked.', [
                'reason' => $trimmedReason,
            ]);
        });
    }

    public function supersede(
        User $actor,
        string $reason,
        ?DocumentRecord $replacement = null,
        ?string $reference = null
    ): void {
        if (! $this->canSupersede()) {
            throw ValidationException::withMessages([
                'workflow' => 'Only Published records may be superseded.',
            ]);
        }

        if ($replacement instanceof DocumentRecord) {
            if ($replacement->is($this)) {
                throw ValidationException::withMessages([
                    'supersedeReplacementDocumentId' => 'The replacement document must be different from the superseded document.',
                ]);
            }

            if ($replacement->status !== DocumentStatus::Published) {
                throw ValidationException::withMessages([
                    'supersedeReplacementDocumentId' => 'The replacement document must be Published.',
                ]);
            }
        }

        $trimmedReason = $this->validatedLifecycleReason($reason, 'supersedeReason');
        $trimmedReference = $reference === null ? null : trim($reference);

        if ($replacement === null && blank($trimmedReference)) {
            throw ValidationException::withMessages([
                'supersedeReference' => 'Select a Published replacement document or enter a replacement reference.',
            ]);
        }

        DB::transaction(function () use ($actor, $replacement, $trimmedReason, $trimmedReference): void {
            $this->forceFill([
                'status' => DocumentStatus::Superseded,
                'superseded_at' => now(),
                'superseded_by_id' => $actor->id,
                'supersede_reason' => $trimmedReason,
                'superseded_by_document_record_id' => $replacement?->id,
                'supersede_reference' => blank($trimmedReference) ? null : $trimmedReference,
            ])->save();

            $this->recordAudit($actor, 'document_superseded', 'Document superseded.', [
                'reason' => $trimmedReason,
                'replacement_document_record_id' => $replacement?->id,
                'replacement_reference' => blank($trimmedReference) ? null : $trimmedReference,
            ]);
        });
    }

    public function currentFileVersion(): ?DocumentFileVersion
    {
        return $this->fileVersions()
            ->where('is_current', true)
            ->latest('version_number')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordAudit(User $actor, string $action, string $summary, array $context = []): AuditLog
    {
        return AuditLog::record($actor, $action, $this, $summary, $context);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'is_project_related' => 'boolean',
            'approved_budget' => 'decimal:2',
            'project_start_date' => 'date',
            'target_completion_date' => 'date',
            'status' => DocumentStatus::class,
            'category_suggestion_terms' => 'array',
            'category_suggested_at' => 'datetime',
            'category_overridden_at' => 'datetime',
            'draft_pdf_uploaded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'archived_at' => 'datetime',
            'revoked_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentRecord $document): void {
            if (blank($document->status)) {
                $document->status = DocumentStatus::Draft;
            }

            if (blank($document->verification_token)) {
                $document->verification_token = self::newVerificationToken();
            }
        });
    }

    private static function newVerificationToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('verification_token', $token)->exists());

        return $token;
    }

    private function validatedLifecycleReason(string $reason, string $field): string
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                $field => 'The reason field is required.',
            ]);
        }

        return $trimmedReason;
    }

    private function notifyCaptains(string $message): void
    {
        $captains = User::query()
            ->role('captain')
            ->where('is_active', true)
            ->get();

        foreach ($captains as $captain) {
            $captain->notify($this->notification(
                message: $message,
                url: route('approvals.index', ['search' => $this->document_number], absolute: false).'#pending-approval-'.$this->id,
            ));
        }
    }

    private function workflowSubmitter(): User
    {
        return $this->submitter()->first() ?? $this->creator;
    }

    private function notification(string $message, ?string $url = null): DocumentWorkflowNotification
    {
        return new DocumentWorkflowNotification(
            documentRecordId: $this->id,
            documentNumber: $this->document_number,
            title: $this->title,
            message: $message,
            url: $url ?? route('documents.show', $this, absolute: false),
        );
    }
}
