<?php

use App\Actions\Documents\SuggestDocumentCategory;
use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Document Record')] class extends Component
{
    use WithFileUploads;

    public DocumentRecord $documentRecord;
    public ?TemporaryUploadedFile $draftPdf = null;
    public string $returnComment = '';
    public string $archiveReason = '';
    public string $revokeReason = '';
    public string $supersedeReason = '';
    public string $supersedeReplacementDocumentId = '';
    public string $supersedeReference = '';
    public bool $editingMetadata = false;
    public string $metadataDocumentTypeId = '';
    public string $metadataDocumentCategoryId = '';
    public ?int $metadataSuggestedDocumentCategoryId = null;
    public ?int $metadataCategorySuggestionRuleId = null;
    /** @var array<int, string> */
    public array $metadataCategorySuggestionTerms = [];
    public ?string $metadataCategorySuggestionReason = null;
    public string $metadataDocumentNumber = '';
    public string $metadataTitle = '';
    public string $metadataDocumentDate = '';
    public string $metadataSubject = '';
    public string $metadataKeywords = '';
    public bool $metadataManualCategoryOverride = false;
    public bool $metadataManualCategoryOverrideExplicit = false;
    public bool $metadataIdentityChangeConfirmed = false;
    public bool $metadataIsProjectRelated = false;
    public string $metadataProjectName = '';
    public string $metadataProjectLocation = '';
    public string $metadataApprovedBudget = '';
    public string $metadataProjectStartDate = '';
    public string $metadataTargetCompletionDate = '';
    public string $metadataProjectRemarks = '';

    public function mount(DocumentRecord $documentRecord): void
    {
        $this->authorize('view', $documentRecord);

        $this->documentRecord = $documentRecord;
        $this->refreshDocument();
    }

    public function startMetadataEdit(): void
    {
        $this->authorize('updateMetadata', $this->documentRecord);

        $this->editingMetadata = true;
        $this->metadataDocumentTypeId = (string) $this->documentRecord->document_type_id;
        $this->metadataDocumentCategoryId = (string) $this->documentRecord->document_category_id;
        $this->metadataDocumentNumber = $this->documentRecord->document_number;
        $this->metadataTitle = $this->documentRecord->title;
        $this->metadataDocumentDate = $this->documentRecord->document_date->toDateString();
        $this->metadataSubject = $this->documentRecord->subject;
        $this->metadataKeywords = $this->documentRecord->keywords ?? '';
        $this->metadataManualCategoryOverride = $this->documentRecord->category_overridden_at !== null;
        $this->metadataManualCategoryOverrideExplicit = $this->metadataManualCategoryOverride;
        $this->metadataIdentityChangeConfirmed = false;
        $this->metadataIsProjectRelated = $this->documentRecord->is_project_related;
        $this->metadataProjectName = $this->documentRecord->project_name ?? '';
        $this->metadataProjectLocation = $this->documentRecord->project_location ?? '';
        $this->metadataApprovedBudget = $this->documentRecord->approved_budget ?? '';
        $this->metadataProjectStartDate = $this->documentRecord->project_start_date?->toDateString() ?? '';
        $this->metadataTargetCompletionDate = $this->documentRecord->target_completion_date?->toDateString() ?? '';
        $this->metadataProjectRemarks = $this->documentRecord->project_remarks ?? '';

        $this->refreshMetadataSuggestedCategory();
    }

    public function cancelMetadataEdit(): void
    {
        $this->resetValidation();
        $this->editingMetadata = false;
        $this->metadataIdentityChangeConfirmed = false;
    }

    public function updatedMetadataDocumentTypeId(): void
    {
        $this->refreshMetadataSuggestedCategory();
        $this->metadataIdentityChangeConfirmed = false;
    }

    public function updatedMetadataDocumentNumber(): void
    {
        $this->metadataIdentityChangeConfirmed = false;
    }

    public function updatedMetadataDocumentDate(): void
    {
        $this->metadataIdentityChangeConfirmed = false;
    }

    public function updatedMetadataTitle(): void
    {
        $this->refreshMetadataSuggestedCategory();
    }

    public function updatedMetadataSubject(): void
    {
        $this->refreshMetadataSuggestedCategory();
    }

    public function updatedMetadataKeywords(): void
    {
        $this->refreshMetadataSuggestedCategory();
    }

    public function beginMetadataManualCategoryOverride(): void
    {
        $this->metadataManualCategoryOverride = true;
        $this->metadataManualCategoryOverrideExplicit = true;
    }

    public function useMetadataSuggestedCategory(): void
    {
        if ($this->metadataSuggestedDocumentCategoryId === null) {
            return;
        }

        $this->metadataManualCategoryOverride = false;
        $this->metadataManualCategoryOverrideExplicit = false;
        $this->metadataDocumentCategoryId = (string) $this->metadataSuggestedDocumentCategoryId;
    }

    public function metadataIdentityChangePending(): bool
    {
        if ((int) $this->metadataDocumentTypeId !== $this->documentRecord->document_type_id) {
            return true;
        }

        if ($this->metadataDocumentNumber !== $this->documentRecord->document_number) {
            return true;
        }

        try {
            return filled($this->metadataDocumentDate)
                && CarbonImmutable::parse($this->metadataDocumentDate)->year !== $this->documentRecord->document_year;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateMetadata(): void
    {
        $this->authorize('updateMetadata', $this->documentRecord);

        $user = $this->user();

        $this->refreshMetadataSuggestedCategory();

        $validated = $this->validate([
            'metadataDocumentTypeId' => [
                'required',
                'integer',
                Rule::exists('document_types', 'id')->where('is_active', true),
            ],
            'metadataDocumentCategoryId' => [
                'required',
                'integer',
                Rule::exists('document_categories', 'id')->where('is_active', true),
            ],
            'metadataDocumentNumber' => ['required', 'string', 'max:64'],
            'metadataTitle' => ['required', 'string', 'max:255'],
            'metadataDocumentDate' => ['required', 'date'],
            'metadataSubject' => ['required', 'string', 'max:2000'],
            'metadataKeywords' => ['nullable', 'string', 'max:1000'],
            'metadataIdentityChangeConfirmed' => ['boolean'],
            'metadataIsProjectRelated' => ['boolean'],
            'metadataProjectName' => ['nullable', 'string', 'max:255'],
            'metadataProjectLocation' => ['nullable', 'string', 'max:255'],
            'metadataApprovedBudget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'metadataProjectStartDate' => ['nullable', 'date'],
            'metadataTargetCompletionDate' => [
                'nullable',
                'date',
                Rule::when(filled($this->metadataProjectStartDate), ['after_or_equal:metadataProjectStartDate']),
            ],
            'metadataProjectRemarks' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'metadataDocumentTypeId' => __('document type'),
            'metadataDocumentCategoryId' => __('primary category'),
            'metadataDocumentNumber' => __('document number'),
            'metadataTitle' => __('title'),
            'metadataDocumentDate' => __('document date'),
            'metadataSubject' => __('subject or description'),
            'metadataKeywords' => __('keywords'),
            'metadataIdentityChangeConfirmed' => __('identity change confirmation'),
            'metadataProjectName' => __('project name'),
            'metadataProjectLocation' => __('project location'),
            'metadataApprovedBudget' => __('approved budget'),
            'metadataProjectStartDate' => __('project start date'),
            'metadataTargetCompletionDate' => __('target completion date'),
            'metadataProjectRemarks' => __('project remarks'),
        ]);

        $documentYear = CarbonImmutable::parse($validated['metadataDocumentDate'])->year;

        $duplicateExists = DocumentRecord::query()
            ->where('document_type_id', (int) $validated['metadataDocumentTypeId'])
            ->where('document_year', $documentYear)
            ->where('document_number', $validated['metadataDocumentNumber'])
            ->whereKeyNot($this->documentRecord->id)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'metadataDocumentNumber' => __('The document number has already been used for this type and year.'),
            ]);
        }

        if ($this->metadataIdentityChanged($documentYear) && ! $validated['metadataIdentityChangeConfirmed']) {
            throw ValidationException::withMessages([
                'metadataIdentityChangeConfirmed' => __('Confirm that the document identity should change while keeping the same permanent QR token.'),
            ]);
        }

        $categoryWasOverridden = $this->metadataCategoryWasOverridden((int) $validated['metadataDocumentCategoryId']);
        $isProjectRelated = (bool) $validated['metadataIsProjectRelated'];

        $this->documentRecord->updateMetadata($user, [
            'document_type_id' => (int) $validated['metadataDocumentTypeId'],
            'document_category_id' => (int) $validated['metadataDocumentCategoryId'],
            'suggested_document_category_id' => $this->metadataSuggestedDocumentCategoryId,
            'category_suggestion_rule_id' => $this->metadataCategorySuggestionRuleId,
            'category_suggestion_terms' => $this->metadataCategorySuggestionTerms === [] ? null : $this->metadataCategorySuggestionTerms,
            'category_suggestion_reason' => $this->metadataCategorySuggestionReason,
            'category_suggested_at' => $this->metadataSuggestedDocumentCategoryId === null ? null : now(),
            'category_overridden_at' => $categoryWasOverridden ? now() : null,
            'category_overridden_by_id' => $categoryWasOverridden ? $user->id : null,
            'document_number' => $validated['metadataDocumentNumber'],
            'document_year' => $documentYear,
            'title' => $validated['metadataTitle'],
            'document_date' => $validated['metadataDocumentDate'],
            'subject' => $validated['metadataSubject'],
            'keywords' => filled($validated['metadataKeywords'] ?? null) ? $validated['metadataKeywords'] : null,
            'is_project_related' => $isProjectRelated,
            'project_name' => $isProjectRelated && filled($validated['metadataProjectName'] ?? null) ? $validated['metadataProjectName'] : null,
            'project_location' => $isProjectRelated && filled($validated['metadataProjectLocation'] ?? null) ? $validated['metadataProjectLocation'] : null,
            'approved_budget' => $isProjectRelated && filled($validated['metadataApprovedBudget'] ?? null) ? $validated['metadataApprovedBudget'] : null,
            'project_start_date' => $isProjectRelated && filled($validated['metadataProjectStartDate'] ?? null) ? $validated['metadataProjectStartDate'] : null,
            'target_completion_date' => $isProjectRelated && filled($validated['metadataTargetCompletionDate'] ?? null) ? $validated['metadataTargetCompletionDate'] : null,
            'project_remarks' => $isProjectRelated && filled($validated['metadataProjectRemarks'] ?? null) ? $validated['metadataProjectRemarks'] : null,
        ], [
            'identity_changed' => $this->metadataIdentityChanged($documentYear),
        ]);

        if ($this->metadataSuggestedDocumentCategoryId !== null) {
            $this->documentRecord->recordAudit($user, 'document_category_suggested', 'Document category suggested.', [
                'suggested_category_id' => $this->metadataSuggestedDocumentCategoryId,
                'rule_id' => $this->metadataCategorySuggestionRuleId,
                'matched_terms' => $this->metadataCategorySuggestionTerms,
                'reason' => $this->metadataCategorySuggestionReason,
            ]);
        }

        if ($categoryWasOverridden) {
            $this->documentRecord->recordAudit($user, 'document_category_overridden', $this->metadataSuggestedDocumentCategoryId === null
                ? 'Document category manually assigned because no automatic rule matched.'
                : 'Document category manually overridden.', [
                'suggested_category_id' => $this->metadataSuggestedDocumentCategoryId,
                'selected_category_id' => (int) $validated['metadataDocumentCategoryId'],
                'rule_id' => $this->metadataCategorySuggestionRuleId,
                'reason' => $this->metadataSuggestedDocumentCategoryId === null ? 'no_rule_matched' : 'manual_correction',
            ]);
        }

        $this->editingMetadata = false;
        $this->metadataIdentityChangeConfirmed = false;
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document metadata updated.'));
    }

    public function uploadDraftPdf(): void
    {
        $this->authorize('uploadDraftPdf', $this->documentRecord);

        $user = $this->user();

        $validated = $this->validate([
            'draftPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], attributes: [
            'draftPdf' => __('draft PDF'),
        ]);

        /** @var TemporaryUploadedFile $uploadedFile */
        $uploadedFile = $validated['draftPdf'];
        $previousPath = $this->documentRecord->draft_pdf_path;

        $path = $uploadedFile->store('document-drafts');

        $this->documentRecord->forceFill([
            'draft_pdf_path' => $path,
            'draft_pdf_original_name' => $uploadedFile->getClientOriginalName(),
            'draft_pdf_mime_type' => $uploadedFile->getMimeType(),
            'draft_pdf_size' => $uploadedFile->getSize(),
            'draft_pdf_uploaded_at' => now(),
            'draft_pdf_uploaded_by_id' => $user->id,
        ])->save();

        if (is_string($previousPath) && $previousPath !== $path) {
            Storage::delete($previousPath);
        }

        $this->draftPdf = null;
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Draft PDF uploaded.'));
    }

    public function submitForApproval(): void
    {
        $this->authorize('submit', $this->documentRecord);

        $this->documentRecord->submitForApproval($this->user());
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document submitted for Captain approval.'));
    }

    public function returnForRevision(): void
    {
        $this->authorize('returnForRevision', $this->documentRecord);

        $this->validate([
            'returnComment' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'returnComment' => __('return comment'),
        ]);

        $this->documentRecord->returnForRevision($this->user(), $this->returnComment);
        $this->returnComment = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document returned for revision.'));
    }

    public function approveForPublication(): void
    {
        $this->authorize('approve', $this->documentRecord);

        $this->documentRecord->approveForPublication($this->user());
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document approved and published.'));
    }

    public function archiveDocument(): void
    {
        $this->authorize('archive', $this->documentRecord);

        $this->validate([
            'archiveReason' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'archiveReason' => __('archive reason'),
        ]);

        $this->documentRecord->archive($this->user(), $this->archiveReason);
        $this->archiveReason = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document archived.'));
    }

    public function revokeDocument(): void
    {
        $this->authorize('revoke', $this->documentRecord);

        $this->validate([
            'revokeReason' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'revokeReason' => __('revoke reason'),
        ]);

        $this->documentRecord->revoke($this->user(), $this->revokeReason);
        $this->revokeReason = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document revoked.'));
    }

    public function supersedeDocument(): void
    {
        $this->authorize('supersede', $this->documentRecord);

        $validated = $this->validate([
            'supersedeReason' => ['required', 'string', 'max:2000'],
            'supersedeReplacementDocumentId' => [
                'nullable',
                'integer',
                Rule::exists('document_records', 'id')
                    ->where('status', DocumentStatus::Published->value)
                    ->where(fn ($query) => $query->where('id', '!=', $this->documentRecord->id)),
            ],
            'supersedeReference' => ['nullable', 'string', 'max:255', 'required_without:supersedeReplacementDocumentId'],
        ], attributes: [
            'supersedeReason' => __('supersede reason'),
            'supersedeReplacementDocumentId' => __('replacement document'),
            'supersedeReference' => __('replacement reference'),
        ]);

        $replacement = filled($validated['supersedeReplacementDocumentId'] ?? null)
            ? DocumentRecord::query()
                ->whereKey((int) $validated['supersedeReplacementDocumentId'])
                ->where('status', DocumentStatus::Published->value)
                ->firstOrFail()
            : null;

        $this->documentRecord->supersede(
            $this->user(),
            $this->supersedeReason,
            $replacement,
            $this->supersedeReference,
        );

        $this->supersedeReason = '';
        $this->supersedeReplacementDocumentId = '';
        $this->supersedeReference = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document superseded.'));
    }

    /**
     * @return EloquentCollection<int, DocumentRecord>
     */
    public function replacementDocuments(): EloquentCollection
    {
        return DocumentRecord::query()
            ->where('status', DocumentStatus::Published->value)
            ->where('id', '!=', $this->documentRecord->id)
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    /**
     * @return EloquentCollection<int, DocumentType>
     */
    #[Computed]
    public function documentTypes(): EloquentCollection
    {
        return DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, DocumentCategory>
     */
    #[Computed]
    public function documentCategories(): EloquentCollection
    {
        return DocumentCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function refreshDocument(): void
    {
        $this->documentRecord->refresh()->load([
            'type',
            'category',
            'suggestedCategory',
            'categorySuggestionRule',
            'creator',
            'categoryOverrider',
            'draftPdfUploader',
            'submitter',
            'approver',
            'returner',
            'archiver',
            'revoker',
            'superseder',
            'supersededByDocumentRecord',
            'fileVersions.approver',
            'auditLogs.actor',
        ]);
    }

    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function refreshMetadataSuggestedCategory(): void
    {
        $suggestion = (new SuggestDocumentCategory)->handle(
            documentTypeId: is_numeric($this->metadataDocumentTypeId) ? (int) $this->metadataDocumentTypeId : null,
            title: $this->metadataTitle,
            subject: $this->metadataSubject,
            keywords: $this->metadataKeywords,
        );

        if ($suggestion === null) {
            $this->metadataSuggestedDocumentCategoryId = null;
            $this->metadataCategorySuggestionRuleId = null;
            $this->metadataCategorySuggestionTerms = [];
            $this->metadataCategorySuggestionReason = null;
            $this->metadataManualCategoryOverride = true;

            return;
        }

        $this->metadataSuggestedDocumentCategoryId = $suggestion->category->id;
        $this->metadataCategorySuggestionRuleId = $suggestion->rule->id;
        $this->metadataCategorySuggestionTerms = $suggestion->matchedTerms;
        $this->metadataCategorySuggestionReason = $suggestion->reason;

        if (! $this->metadataManualCategoryOverrideExplicit || blank($this->metadataDocumentCategoryId)) {
            $this->metadataManualCategoryOverride = false;
            $this->metadataManualCategoryOverrideExplicit = false;
            $this->metadataDocumentCategoryId = (string) $suggestion->category->id;
        }
    }

    private function metadataCategoryWasOverridden(int $selectedCategoryId): bool
    {
        return $this->metadataManualCategoryOverride
            && ($this->metadataSuggestedDocumentCategoryId === null
                || $this->metadataSuggestedDocumentCategoryId !== $selectedCategoryId);
    }

    private function metadataIdentityChanged(int $documentYear): bool
    {
        return (int) $this->metadataDocumentTypeId !== $this->documentRecord->document_type_id
            || $documentYear !== $this->documentRecord->document_year
            || $this->metadataDocumentNumber !== $this->documentRecord->document_number;
    }
}; ?>

@php
    $currentUser = auth()->user();
    $canReturn = $currentUser instanceof User && $currentUser->can('returnForRevision', $documentRecord);
    $canApprove = $currentUser instanceof User && $currentUser->can('approve', $documentRecord);
    $canArchive = $currentUser instanceof User && $currentUser->can('archive', $documentRecord);
    $canRevoke = $currentUser instanceof User && $currentUser->can('revoke', $documentRecord);
    $canSupersede = $currentUser instanceof User && $currentUser->can('supersede', $documentRecord);
    $canUploadDraftPdf = $currentUser instanceof User && $currentUser->can('uploadDraftPdf', $documentRecord);
    $canDownloadDraftPdf = $currentUser instanceof User && $currentUser->can('downloadDraftPdf', $documentRecord);
    $canDownloadQrCode = $currentUser instanceof User && $currentUser->can('downloadQrCode', $documentRecord);
    $canSubmit = $currentUser instanceof User && $currentUser->can('submit', $documentRecord);
    $canUpdateMetadata = $currentUser instanceof User && $currentUser->can('updateMetadata', $documentRecord);
    $mayUploadDraftPdf = $currentUser instanceof User && $currentUser->can('documents.upload-draft-pdf');
    $maySubmitDocuments = $currentUser instanceof User && $currentUser->can('documents.submit');
    $currentVersion = $documentRecord->currentFileVersion();
    $officialVersions = $documentRecord->fileVersions->sortByDesc('version_number');
    $replacementDocuments = $this->replacementDocuments();
@endphp

<section class="app-content max-w-6xl">
    <x-page-header
        :eyebrow="$documentRecord->document_number"
        :title="$documentRecord->title"
        :description="__('Manage the record, QR identity, PDF review, approval workflow, and public verification state.')"
    >
        <x-slot:actions>
            <flux:button variant="outline" icon="arrow-left" :href="route('documents.index')" wire:navigate>{{ __('Back to archive') }}</flux:button>
            @if ($documentRecord->hasDraftPdf() && $canDownloadDraftPdf)
                <flux:button variant="outline" icon="document-text" :href="route('documents.draft-pdf', $documentRecord)" target="_blank">{{ __('Review Draft PDF') }}</flux:button>
            @endif
            @if ($canDownloadQrCode)
                <flux:button variant="primary" icon="qr-code" :href="route('documents.qr-code', $documentRecord)">{{ __('Download QR') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="grid gap-5">
            <x-panel :title="__('Document metadata')" :description="__('Public-safe identity fields for the permanent verification page.')">
                @if ($canUpdateMetadata)
                    <x-slot:actions>
                        @if ($editingMetadata)
                            <flux:button size="sm" variant="outline" type="button" wire:click="cancelMetadataEdit">{{ __('Cancel') }}</flux:button>
                        @else
                            <flux:button size="sm" variant="outline" icon="pencil-square" type="button" wire:click="startMetadataEdit">{{ __('Edit Metadata') }}</flux:button>
                        @endif
                    </x-slot:actions>
                @endif

                @if ($editingMetadata)
                    <form wire:submit="updateMetadata" class="grid gap-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Document type') }} <span class="text-red-600">*</span></flux:label>
                                <flux:select wire:model.live="metadataDocumentTypeId">
                                    <flux:select.option value="">{{ __('Select document type') }}</flux:select.option>
                                    @foreach ($this->documentTypes as $documentType)
                                        <flux:select.option :value="$documentType->id" wire:key="metadata-document-type-{{ $documentType->id }}">{{ $documentType->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="metadataDocumentTypeId" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Document number') }} <span class="text-red-600">*</span></flux:label>
                                <flux:input wire:model.live.blur="metadataDocumentNumber" />
                                <flux:error name="metadataDocumentNumber" />
                            </flux:field>

                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Title') }} <span class="text-red-600">*</span></flux:label>
                                <flux:input wire:model.live.blur="metadataTitle" />
                                <flux:error name="metadataTitle" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Document date') }} <span class="text-red-600">*</span></flux:label>
                                <flux:input wire:model.live="metadataDocumentDate" type="date" />
                                <flux:error name="metadataDocumentDate" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Primary category') }} <span class="text-red-600">*</span></flux:label>
                                @if ($metadataSuggestedDocumentCategoryId !== null && ! $metadataManualCategoryOverride)
                                    @php($suggestedCategory = $this->documentCategories->firstWhere('id', $metadataSuggestedDocumentCategoryId))
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                                        <p class="font-semibold">{{ __('Automatically assigned: :category', ['category' => $suggestedCategory?->name ?? __('Unknown category')]) }}</p>
                                        <p class="mt-1 text-xs">{{ $metadataCategorySuggestionReason }}</p>
                                        <flux:button class="mt-3" size="sm" variant="outline" type="button" wire:click="beginMetadataManualCategoryOverride">{{ __('Correct category') }}</flux:button>
                                    </div>
                                @else
                                    @if ($metadataSuggestedDocumentCategoryId === null)
                                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                            <p class="font-semibold">{{ __('No automatic category matched') }}</p>
                                            <p class="mt-1 text-xs">{{ __('Confirm or change the category before saving this exceptional record.') }}</p>
                                        </div>
                                    @else
                                        @php($suggestedCategory = $this->documentCategories->firstWhere('id', $metadataSuggestedDocumentCategoryId))
                                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                            <p class="font-semibold">{{ __('Automatic result: :category', ['category' => $suggestedCategory?->name ?? __('Unknown category')]) }}</p>
                                            <p class="mt-1 text-xs">{{ __('Choose a correction below, or return to the automatic result.') }}</p>
                                            <flux:button class="mt-3" size="sm" variant="outline" type="button" wire:click="useMetadataSuggestedCategory">{{ __('Use automatic category') }}</flux:button>
                                        </div>
                                    @endif

                                    <flux:select wire:model="metadataDocumentCategoryId">
                                        <flux:select.option value="">{{ __('Select primary category') }}</flux:select.option>
                                        @foreach ($this->documentCategories as $documentCategory)
                                            <flux:select.option :value="$documentCategory->id" wire:key="metadata-document-category-{{ $documentCategory->id }}">{{ $documentCategory->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                                <flux:error name="metadataDocumentCategoryId" />
                            </flux:field>

                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Subject or description') }} <span class="text-red-600">*</span></flux:label>
                                <flux:textarea wire:model.live.blur="metadataSubject" rows="4" />
                                <flux:error name="metadataSubject" />
                            </flux:field>

                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Keywords') }}</flux:label>
                                <flux:input wire:model.live.blur="metadataKeywords" />
                                <flux:error name="metadataKeywords" />
                            </flux:field>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <flux:checkbox wire:model.live="metadataIsProjectRelated" :label="__('This document is project-related')" />

                            @if ($metadataIsProjectRelated)
                                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                    <flux:field>
                                        <flux:label>{{ __('Project name') }}</flux:label>
                                        <flux:input wire:model="metadataProjectName" />
                                        <flux:error name="metadataProjectName" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Project location') }}</flux:label>
                                        <flux:input wire:model="metadataProjectLocation" />
                                        <flux:error name="metadataProjectLocation" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Approved budget') }}</flux:label>
                                        <flux:input wire:model="metadataApprovedBudget" type="number" min="0" step="0.01" />
                                        <flux:error name="metadataApprovedBudget" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Project start date') }}</flux:label>
                                        <flux:input wire:model="metadataProjectStartDate" type="date" />
                                        <flux:error name="metadataProjectStartDate" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Target completion date') }}</flux:label>
                                        <flux:input wire:model="metadataTargetCompletionDate" type="date" />
                                        <flux:error name="metadataTargetCompletionDate" />
                                    </flux:field>

                                    <flux:field class="sm:col-span-2">
                                        <flux:label>{{ __('Project remarks') }}</flux:label>
                                        <flux:textarea wire:model="metadataProjectRemarks" rows="3" />
                                        <flux:error name="metadataProjectRemarks" />
                                    </flux:field>
                                </div>
                            @endif
                        </div>

                        @if ($this->metadataIdentityChangePending())
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                <flux:checkbox wire:model="metadataIdentityChangeConfirmed" :label="__('Confirm this document identity change while keeping the same permanent QR token')" />
                                <flux:error name="metadataIdentityChangeConfirmed" />
                            </div>
                        @endif

                        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end dark:border-slate-700">
                            <flux:button variant="outline" type="button" wire:click="cancelMetadataEdit">{{ __('Cancel') }}</flux:button>
                            <flux:button variant="primary" type="submit" icon="check" wire:loading.attr="disabled" wire:target="updateMetadata">
                                <span wire:loading.remove wire:target="updateMetadata">{{ __('Save Metadata') }}</span>
                                <span wire:loading wire:target="updateMetadata">{{ __('Saving...') }}</span>
                            </flux:button>
                        </div>
                    </form>
                @else
                    <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        @foreach ([['Document number', $documentRecord->document_number], ['Document type', $documentRecord->type->name], ['Primary category', $documentRecord->category->name], ['Document date', $documentRecord->document_date->toFormattedDateString()], ['Status', $documentRecord->status->label()], ['Created by', $documentRecord->creator->name]] as [$label, $value])
                            <div class="border-b border-slate-100 pb-4 dark:border-slate-800" wire:key="metadata-{{ str($label)->slug() }}">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Subject or description') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $documentRecord->subject }}</p>
                    </div>

                    @if ($documentRecord->is_project_related)
                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Project metadata') }}</p>
                            <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                                @foreach ([['Project name', $documentRecord->project_name], ['Project location', $documentRecord->project_location], ['Approved budget', $documentRecord->approved_budget === null ? null : 'PHP '.number_format((float) $documentRecord->approved_budget, 2)], ['Project start date', $documentRecord->project_start_date?->toFormattedDateString()], ['Target completion date', $documentRecord->target_completion_date?->toFormattedDateString()]] as [$label, $value])
                                    @if (filled($value))
                                        <div wire:key="project-metadata-{{ str($label)->slug() }}">
                                            <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt>
                                            <dd class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $value }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                            @if ($documentRecord->project_remarks)
                                <p class="mt-4 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $documentRecord->project_remarks }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($documentRecord->suggestedCategory)
                        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                            <p class="text-xs font-bold uppercase tracking-wider">{{ __('Automatic categorization') }}</p>
                            <p class="mt-2 font-semibold">{{ __('Assigned :suggested from :rule', ['suggested' => $documentRecord->suggestedCategory->name, 'rule' => $documentRecord->categorySuggestionRule?->name ?? __('a deleted rule')]) }}</p>
                            @if ($documentRecord->category_suggestion_reason)
                                <p class="mt-1 text-xs leading-5">{{ $documentRecord->category_suggestion_reason }}</p>
                            @endif
                            @if ($documentRecord->category_overridden_at)
                                <p class="mt-2 text-xs leading-5">{{ __('Final category was manually set to :category by :actor.', ['category' => $documentRecord->category->name, 'actor' => $documentRecord->categoryOverrider?->name ?? __('authorized staff')]) }}</p>
                            @endif
                        </div>
                    @elseif ($documentRecord->category_overridden_at)
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            <p class="text-xs font-bold uppercase tracking-wider">{{ __('Manual categorization') }}</p>
                            <p class="mt-2 font-semibold">{{ __('No automatic rule matched. :category was assigned by :actor.', ['category' => $documentRecord->category->name, 'actor' => $documentRecord->categoryOverrider?->name ?? __('authorized staff')]) }}</p>
                        </div>
                    @endif
                @endif
            </x-panel>

            <x-panel :title="__('Draft PDF')" :description="__('Upload the prepared PDF after placing the downloaded QR into the external document file.')">
                @if ($documentRecord->hasDraftPdf())
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <p class="font-semibold">{{ $documentRecord->draft_pdf_original_name }}</p>
                        <p class="mt-1 text-xs">{{ __('Uploaded :date by :name', ['date' => $documentRecord->draft_pdf_uploaded_at?->toDayDateTimeString(), 'name' => $documentRecord->draftPdfUploader?->name ?? __('Unknown staff')]) }}</p>
                        <flux:button class="mt-3" size="sm" variant="outline" icon="eye" :href="route('documents.draft-pdf', $documentRecord)" target="_blank">{{ __('Open Draft PDF') }}</flux:button>
                    </div>
                @endif

                @if ($canUploadDraftPdf)
                    <form wire:submit="uploadDraftPdf" class="grid gap-4">
                        <flux:field>
                            <flux:label>{{ __('PDF file') }} <span class="text-red-600">*</span></flux:label>
                            <flux:input wire:model="draftPdf" type="file" accept="application/pdf" />
                            <flux:description>{{ __('PDF only, up to 10 MB. Replacing a Draft or Needs Revision PDF removes the previous working copy.') }}</flux:description>
                            <flux:error name="draftPdf" />
                        </flux:field>

                        <div class="flex justify-end border-t border-slate-200 pt-4 dark:border-slate-700">
                            <flux:button variant="primary" type="submit" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="draftPdf,uploadDraftPdf">
                                <span wire:loading.remove wire:target="uploadDraftPdf">{{ $documentRecord->hasDraftPdf() ? __('Replace Draft PDF') : __('Upload Draft PDF') }}</span>
                                <span wire:loading wire:target="uploadDraftPdf">{{ __('Uploading...') }}</span>
                            </flux:button>
                        </div>
                    </form>
                @elseif ($mayUploadDraftPdf)
                    <x-foundation-notice :title="__('PDF replacement locked')">{{ __('PDF replacement is available only while the document is Draft or Needs Revision. Published PDFs are preserved as immutable official versions.') }}</x-foundation-notice>
                @else
                    <x-foundation-notice :title="__('Secretary preparation only')">{{ __('Only the Barangay Secretary can upload or replace a working PDF. Captain access is limited to review and final decisions.') }}</x-foundation-notice>
                @endif
            </x-panel>

            <x-panel :title="__('Official PDF history')" :description="__('Approved versions are preserved newest first and never silently overwritten.')">
                @if ($officialVersions->isEmpty())
                    <x-empty-state :title="__('No official PDF versions yet')" :description="__('Captain approval will preserve the first official version here.')" icon="document-text" />
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($officialVersions as $fileVersion)
                            <div class="flex items-start justify-between gap-4 py-3" wire:key="official-version-{{ $fileVersion->id }}">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Version :version', ['version' => $fileVersion->version_number]) }}</p>
                                    <p class="mt-1 break-all text-xs text-slate-500 dark:text-slate-400">{{ $fileVersion->original_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Approved :date by :name', ['date' => $fileVersion->approved_at->toDayDateTimeString(), 'name' => $fileVersion->approver?->name ?? __('Captain')]) }}</p>
                                </div>
                                @if ($fileVersion->is_current)
                                    <flux:badge color="emerald">{{ __('Current') }}</flux:badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel>

            <x-panel :title="__('Workflow history')" :description="__('Audit-safe state history recorded by the application.')">
                @if ($documentRecord->auditLogs->isEmpty())
                    <x-empty-state :title="__('No workflow events yet')" :description="__('Submission, return, and approval events will appear here after staff actions.')" icon="clock" />
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($documentRecord->auditLogs->sortByDesc('created_at') as $auditLog)
                            <div class="py-3" wire:key="audit-log-{{ $auditLog->id }}">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $auditLog->summary }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __(':actor · :time', ['actor' => $auditLog->actor?->name ?? __('System'), 'time' => $auditLog->created_at?->toDayDateTimeString()]) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel>
        </div>

        <aside class="grid content-start gap-5">
            <x-panel :title="__('Approval workflow')">
                <x-status-badge :status="$documentRecord->status->value" />
                <flux:error name="workflow" />

                @if ($documentRecord->submitted_at)
                    <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ __('Submitted :date by :name', ['date' => $documentRecord->submitted_at->toDayDateTimeString(), 'name' => $documentRecord->submitter?->name ?? __('Unknown staff')]) }}</p>
                @endif

                @if ($documentRecord->return_comment)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">
                        <p class="text-xs font-bold uppercase tracking-wider">{{ __('Latest return comment') }}</p>
                        <p class="mt-2 leading-6">{{ $documentRecord->return_comment }}</p>
                        <p class="mt-2 text-xs">{{ __('Returned :date by :name', ['date' => $documentRecord->returned_at?->toDayDateTimeString(), 'name' => $documentRecord->returner?->name ?? __('Captain')]) }}</p>
                    </div>
                @endif

                @if ($documentRecord->status === DocumentStatus::Published)
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <p class="font-semibold">{{ __('Published official version') }}</p>
                        <p class="mt-1 text-xs">{{ __('Approved :date by :name', ['date' => $documentRecord->approved_at?->toDayDateTimeString(), 'name' => $documentRecord->approver?->name ?? __('Captain')]) }}</p>
                        @if ($currentVersion)
                            <p class="mt-1 text-xs">{{ __('Version :version · :name', ['version' => $currentVersion->version_number, 'name' => $currentVersion->original_name]) }}</p>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-2">
                        <flux:button variant="primary" icon="shield-check" :href="$documentRecord->verificationUrl()" target="_blank">{{ __('Open public verification') }}</flux:button>
                        @if ($currentVersion)
                            <flux:button variant="outline" icon="document-arrow-down" :href="$documentRecord->publicDocumentUrl()" target="_blank">{{ __('View official PDF') }}</flux:button>
                        @endif
                    </div>

                    @if ($canArchive || $canRevoke || $canSupersede)
                        <div class="mt-5 grid gap-5 border-t border-slate-200 pt-5 dark:border-slate-700">
                            @if ($canArchive)
                                <form wire:submit="archiveDocument" class="grid gap-3">
                                    <flux:field>
                                        <flux:label>{{ __('Archive reason') }} <span class="text-red-600">*</span></flux:label>
                                        <flux:textarea wire:model="archiveReason" rows="3" :placeholder="__('Explain why this official record is no longer current.')" />
                                        <flux:error name="archiveReason" />
                                    </flux:field>
                                    <flux:button variant="outline" type="submit" icon="archive-box" wire:confirm="{{ __('Archive this published document? The public verification page will remain available and the official PDF will stay viewable.') }}">
                                        {{ __('Archive Document') }}
                                    </flux:button>
                                </form>
                            @endif

                            @if ($canRevoke)
                                <form wire:submit="revokeDocument" class="grid gap-3">
                                    <flux:field>
                                        <flux:label>{{ __('Revoke reason') }} <span class="text-red-600">*</span></flux:label>
                                        <flux:textarea wire:model="revokeReason" rows="3" :placeholder="__('Explain why this document is no longer valid.')" />
                                        <flux:error name="revokeReason" />
                                    </flux:field>
                                    <flux:button variant="danger" type="submit" icon="no-symbol" wire:confirm="{{ __('Revoke this published document? The public page will show it as invalid and remove public PDF actions.') }}">
                                        {{ __('Revoke Document') }}
                                    </flux:button>
                                </form>
                            @endif

                            @if ($canSupersede)
                                <form wire:submit="supersedeDocument" class="grid gap-3">
                                    <flux:field>
                                        <flux:label>{{ __('Supersede reason') }} <span class="text-red-600">*</span></flux:label>
                                        <flux:textarea wire:model="supersedeReason" rows="3" :placeholder="__('Explain why a replacement document now controls.')" />
                                        <flux:error name="supersedeReason" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Replacement document') }}</flux:label>
                                        <flux:select wire:model="supersedeReplacementDocumentId">
                                            <flux:select.option value="">{{ __('Use reference text instead') }}</flux:select.option>
                                            @foreach ($replacementDocuments as $replacementDocument)
                                                <flux:select.option :value="$replacementDocument->id" wire:key="replacement-document-{{ $replacementDocument->id }}">
                                                    {{ $replacementDocument->document_number }} - {{ $replacementDocument->title }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="supersedeReplacementDocumentId" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Replacement reference') }}</flux:label>
                                        <flux:input wire:model="supersedeReference" :placeholder="__('Resolution No. 2026-100 or external reference')" />
                                        <flux:error name="supersedeReference" />
                                    </flux:field>

                                    <flux:button variant="outline" type="submit" icon="arrow-path" wire:confirm="{{ __('Mark this published document as superseded? The public page will show replacement context and remove public PDF actions.') }}">
                                        {{ __('Supersede Document') }}
                                    </flux:button>
                                </form>
                            @endif
                        </div>
                    @endif
                @elseif ($documentRecord->status === DocumentStatus::PendingApproval)
                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('This document is waiting for Captain review. The public verification page remains Not Yet Verified until approval.') }}</p>

                    @if ($canReturn || $canApprove)
                        <div class="mt-5 grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                            @if ($canReturn)
                                <form wire:submit="returnForRevision" class="grid gap-3">
                                    <flux:field>
                                        <flux:label>{{ __('Return comment') }}</flux:label>
                                        <flux:textarea wire:model="returnComment" rows="4" :placeholder="__('Explain what must be corrected before approval.')" />
                                        <flux:error name="returnComment" />
                                    </flux:field>
                                    <flux:button variant="outline" type="submit" icon="arrow-uturn-left">{{ __('Return for revision') }}</flux:button>
                                </form>
                            @endif

                            @if ($canApprove)
                                <flux:button variant="primary" icon="shield-check" wire:click="approveForPublication" wire:confirm="{{ __('Approve and publish this document? The current Draft PDF will be preserved as the official public version.') }}">
                                    {{ __('Approve and Publish') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
                @elseif (in_array($documentRecord->status, [DocumentStatus::Draft, DocumentStatus::NeedsRevision], true))
                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('Draft and Needs Revision records remain Not Yet Verified on the public page. Submit after the Draft PDF is ready for Captain review.') }}</p>

                    @if ($canSubmit)
                        <flux:button class="mt-5 w-full" variant="primary" icon="paper-airplane" wire:click="submitForApproval" wire:confirm="{{ __('Submit this document for Captain approval?') }}">
                            {{ __('Submit for Approval') }}
                        </flux:button>
                    @elseif ($maySubmitDocuments)
                        <x-foundation-notice class="mt-5" :title="__('Draft PDF required')">{{ __('Upload a prepared Draft PDF before submitting this record for approval.') }}</x-foundation-notice>
                    @else
                        <x-foundation-notice class="mt-5" :title="__('Secretary submission required')">{{ __('Only the Barangay Secretary can submit or resubmit a prepared document for Captain review.') }}</x-foundation-notice>
                    @endif
                @else
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                        @if ($documentRecord->status === DocumentStatus::Archived)
                            <p class="font-semibold text-slate-900 dark:text-white">{{ __('Archived official record') }}</p>
                            <p class="mt-2 leading-6">{{ $documentRecord->archive_reason }}</p>
                            <p class="mt-2 text-xs">{{ __('Archived :date by :name', ['date' => $documentRecord->archived_at?->toDayDateTimeString(), 'name' => $documentRecord->archiver?->name ?? __('Captain')]) }}</p>
                        @elseif ($documentRecord->status === DocumentStatus::Revoked)
                            <p class="font-semibold text-slate-900 dark:text-white">{{ __('Revoked document') }}</p>
                            <p class="mt-2 leading-6">{{ $documentRecord->revoke_reason }}</p>
                            <p class="mt-2 text-xs">{{ __('Revoked :date by :name', ['date' => $documentRecord->revoked_at?->toDayDateTimeString(), 'name' => $documentRecord->revoker?->name ?? __('Captain')]) }}</p>
                        @elseif ($documentRecord->status === DocumentStatus::Superseded)
                            <p class="font-semibold text-slate-900 dark:text-white">{{ __('Superseded document') }}</p>
                            <p class="mt-2 leading-6">{{ $documentRecord->supersede_reason }}</p>
                            <p class="mt-2 text-xs">{{ __('Superseded :date by :name', ['date' => $documentRecord->superseded_at?->toDayDateTimeString(), 'name' => $documentRecord->superseder?->name ?? __('Captain')]) }}</p>
                            @if ($documentRecord->supersededByDocumentRecord)
                                <a class="mt-3 block text-sm font-semibold text-emerald-800 hover:underline dark:text-emerald-300" href="{{ $documentRecord->supersededByDocumentRecord->verificationUrl() }}" target="_blank">
                                    {{ __('Replacement: :number', ['number' => $documentRecord->supersededByDocumentRecord->document_number]) }}
                                </a>
                            @elseif ($documentRecord->supersede_reference)
                                <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('Replacement: :reference', ['reference' => $documentRecord->supersede_reference]) }}</p>
                            @endif
                        @endif
                    </div>

                    <div class="mt-4 grid gap-2">
                        <flux:button variant="primary" icon="shield-check" :href="$documentRecord->verificationUrl()" target="_blank">{{ __('Open public verification') }}</flux:button>
                        @if ($documentRecord->hasPublicPdfAccess() && $currentVersion)
                            <flux:button variant="outline" icon="document-arrow-down" :href="$documentRecord->publicDocumentUrl()" target="_blank">{{ __('View official PDF') }}</flux:button>
                        @endif
                    </div>
                @endif
            </x-panel>

            <x-panel :title="__('Permanent verification')">
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Public URL') }}</p>
                    <a class="mt-2 block break-all text-sm font-semibold text-emerald-800 hover:underline dark:text-emerald-300" href="{{ $documentRecord->verificationUrl() }}" target="_blank">
                        {{ $documentRecord->verificationUrl() }}
                    </a>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    @if ($canDownloadQrCode)
                        <flux:button variant="primary" icon="qr-code" :href="route('documents.qr-code', $documentRecord)">{{ __('Download QR SVG') }}</flux:button>
                    @endif
                    <flux:button variant="outline" icon="shield-check" :href="$documentRecord->verificationUrl()" target="_blank">{{ __('Open public verification') }}</flux:button>
                </div>
            </x-panel>
        </aside>
    </div>
</section>
