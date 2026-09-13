<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\DocumentRecord;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function mount(DocumentRecord $documentRecord): void
    {
        $this->documentRecord = $documentRecord;
        $this->refreshDocument();
    }

    public function uploadDraftPdf(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role->canManageDocuments(), 403);
        abort_unless($this->documentRecord->canReplaceDraftPdf(), 403);

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
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role->canManageDocuments(), 403);

        $this->documentRecord->submitForApproval($user);
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document submitted for Captain approval.'));
    }

    public function returnForRevision(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role === UserRole::Captain, 403);

        $this->validate([
            'returnComment' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'returnComment' => __('return comment'),
        ]);

        $this->documentRecord->returnForRevision($user, $this->returnComment);
        $this->returnComment = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document returned for revision.'));
    }

    public function approveForPublication(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role === UserRole::Captain, 403);

        $this->documentRecord->approveForPublication($user);
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document approved and published.'));
    }

    public function archiveDocument(): void
    {
        $user = $this->captain();

        $this->validate([
            'archiveReason' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'archiveReason' => __('archive reason'),
        ]);

        $this->documentRecord->archive($user, $this->archiveReason);
        $this->archiveReason = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document archived.'));
    }

    public function revokeDocument(): void
    {
        $user = $this->captain();

        $this->validate([
            'revokeReason' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'revokeReason' => __('revoke reason'),
        ]);

        $this->documentRecord->revoke($user, $this->revokeReason);
        $this->revokeReason = '';
        $this->refreshDocument();

        Flux::toast(variant: 'success', text: __('Document revoked.'));
    }

    public function supersedeDocument(): void
    {
        $user = $this->captain();

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
            $user,
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

    private function captain(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role === UserRole::Captain, 403);

        return $user;
    }
}; ?>

@php
    $currentUser = auth()->user();
    $isCaptain = $currentUser instanceof User && $currentUser->role === UserRole::Captain;
    $canSubmit = $documentRecord->canSubmitForApproval();
    $canUploadDraftPdf = $documentRecord->canReplaceDraftPdf();
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
            @if ($documentRecord->hasDraftPdf())
                <flux:button variant="outline" icon="document-text" :href="route('documents.draft-pdf', $documentRecord)" target="_blank">{{ __('Review Draft PDF') }}</flux:button>
            @endif
            <flux:button variant="primary" icon="qr-code" :href="route('documents.qr-code', $documentRecord)">{{ __('Download QR') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="grid gap-5">
            <x-panel :title="__('Document metadata')" :description="__('Public-safe identity fields for the permanent verification page.')">
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

                @if ($documentRecord->suggestedCategory)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <p class="text-xs font-bold uppercase tracking-wider">{{ __('Categorization suggestion') }}</p>
                        <p class="mt-2 font-semibold">{{ __('Suggested :suggested from :rule', ['suggested' => $documentRecord->suggestedCategory->name, 'rule' => $documentRecord->categorySuggestionRule?->name ?? __('a deleted rule')]) }}</p>
                        @if ($documentRecord->category_suggestion_reason)
                            <p class="mt-1 text-xs leading-5">{{ $documentRecord->category_suggestion_reason }}</p>
                        @endif
                        @if ($documentRecord->category_overridden_at)
                            <p class="mt-2 text-xs leading-5">{{ __('Final category was manually set to :category by :actor.', ['category' => $documentRecord->category->name, 'actor' => $documentRecord->categoryOverrider?->name ?? __('authorized staff')]) }}</p>
                        @endif
                    </div>
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
                @else
                    <x-foundation-notice :title="__('PDF replacement locked')">{{ __('PDF replacement is available only while the document is Draft or Needs Revision. Published PDFs are preserved as immutable official versions.') }}</x-foundation-notice>
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

                    @if ($isCaptain)
                        <div class="mt-5 grid gap-5 border-t border-slate-200 pt-5 dark:border-slate-700">
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
                        </div>
                    @endif
                @elseif ($documentRecord->status === DocumentStatus::PendingApproval)
                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('This document is waiting for Captain review. The public verification page remains Not Yet Verified until approval.') }}</p>

                    @if ($isCaptain)
                        <div class="mt-5 grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                            <form wire:submit="returnForRevision" class="grid gap-3">
                                <flux:field>
                                    <flux:label>{{ __('Return comment') }}</flux:label>
                                    <flux:textarea wire:model="returnComment" rows="4" :placeholder="__('Explain what must be corrected before approval.')" />
                                    <flux:error name="returnComment" />
                                </flux:field>
                                <flux:button variant="outline" type="submit" icon="arrow-uturn-left">{{ __('Return for revision') }}</flux:button>
                            </form>

                            <flux:button variant="primary" icon="shield-check" wire:click="approveForPublication" wire:confirm="{{ __('Approve and publish this document? The current Draft PDF will be preserved as the official public version.') }}">
                                {{ __('Approve and Publish') }}
                            </flux:button>
                        </div>
                    @endif
                @elseif (in_array($documentRecord->status, [DocumentStatus::Draft, DocumentStatus::NeedsRevision], true))
                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('Draft and Needs Revision records remain Not Yet Verified on the public page. Submit after the Draft PDF is ready for Captain review.') }}</p>

                    @if ($canSubmit)
                        <flux:button class="mt-5 w-full" variant="primary" icon="paper-airplane" wire:click="submitForApproval" wire:confirm="{{ __('Submit this document for Captain approval?') }}">
                            {{ __('Submit for Approval') }}
                        </flux:button>
                    @else
                        <x-foundation-notice class="mt-5" :title="__('Draft PDF required')">{{ __('Upload a prepared Draft PDF before submitting this record for approval.') }}</x-foundation-notice>
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
                    <flux:button variant="primary" icon="qr-code" :href="route('documents.qr-code', $documentRecord)">{{ __('Download QR SVG') }}</flux:button>
                    <flux:button variant="outline" icon="shield-check" :href="$documentRecord->verificationUrl()" target="_blank">{{ __('Open public verification') }}</flux:button>
                </div>
            </x-panel>
        </aside>
    </div>
</section>
