<?php

use App\Actions\Documents\SuggestDocumentCategory;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use App\Services\ResidentDocumentRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Document Record')] class extends Component
{
    public string $documentTypeId = '';
    public string $documentCategoryId = '';
    public ?int $suggestedDocumentCategoryId = null;
    public ?int $categorySuggestionRuleId = null;
    /** @var array<int, string> */
    public array $categorySuggestionTerms = [];
    public ?string $categorySuggestionReason = null;
    public string $documentNumber = '';
    public string $title = '';
    public string $documentDate = '';
    public string $subject = '';
    public string $keywords = '';
    public bool $isProjectRelated = false;
    public string $projectName = '';
    public string $projectLocation = '';
    public string $approvedBudget = '';
    public string $projectStartDate = '';
    public string $targetCompletionDate = '';
    public string $projectRemarks = '';
    public bool $documentNumberWasEdited = false;
    public bool $manualCategoryOverride = false;
    public ?int $residentDocumentRequestId = null;

    public function mount(?ResidentDocumentRequest $residentDocumentRequest = null): void
    {
        $this->authorize('create', DocumentRecord::class);

        $this->documentDate = now()->toDateString();

        if ($residentDocumentRequest instanceof ResidentDocumentRequest) {
            abort_unless(Auth::user()?->can('process', $residentDocumentRequest), 404);
            $residentDocumentRequest->loadMissing('documentType');
            $this->residentDocumentRequestId = $residentDocumentRequest->id;
            $this->documentTypeId = (string) $residentDocumentRequest->document_type_id;
            $this->title = $residentDocumentRequest->documentType->name.' - '.$residentDocumentRequest->resident_name_snapshot;
            $this->subject = $residentDocumentRequest->purpose;
            $this->refreshSuggestedDocumentNumber(force: true);
            $this->refreshSuggestedCategory();
        }
    }

    public function updatedDocumentTypeId(): void
    {
        $this->refreshSuggestedDocumentNumber();
        $this->refreshSuggestedCategory();
    }

    public function updatedDocumentDate(): void
    {
        $this->refreshSuggestedDocumentNumber();
    }

    public function updatedDocumentNumber(): void
    {
        $this->documentNumberWasEdited = filled($this->documentNumber);
    }

    public function updatedTitle(): void
    {
        $this->refreshSuggestedCategory();
    }

    public function updatedSubject(): void
    {
        $this->refreshSuggestedCategory();
    }

    public function updatedKeywords(): void
    {
        $this->refreshSuggestedCategory();
    }

    public function beginManualCategoryOverride(): void
    {
        $this->manualCategoryOverride = true;
    }

    public function useSuggestedDocumentNumber(): void
    {
        $this->documentNumberWasEdited = false;
        $this->refreshSuggestedDocumentNumber(force: true);
    }

    public function useSuggestedCategory(): void
    {
        if ($this->suggestedDocumentCategoryId === null) {
            return;
        }

        $this->manualCategoryOverride = false;
        $this->documentCategoryId = (string) $this->suggestedDocumentCategoryId;
    }

    public function save(): RedirectResponse
    {
        $this->authorize('create', DocumentRecord::class);

        /** @var User $user */
        $user = Auth::user();

        $this->refreshSuggestedCategory();

        $validated = $this->validate([
            'documentTypeId' => [
                'required',
                'integer',
                Rule::exists('document_types', 'id')->where('is_active', true),
            ],
            'documentCategoryId' => [
                'required',
                'integer',
                Rule::exists('document_categories', 'id')->where('is_active', true),
            ],
            'documentNumber' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'documentDate' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'isProjectRelated' => ['boolean'],
            'projectName' => ['nullable', 'string', 'max:255'],
            'projectLocation' => ['nullable', 'string', 'max:255'],
            'approvedBudget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'projectStartDate' => ['nullable', 'date'],
            'targetCompletionDate' => [
                'nullable',
                'date',
                Rule::when(filled($this->projectStartDate), ['after_or_equal:projectStartDate']),
            ],
            'projectRemarks' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'documentTypeId' => __('document type'),
            'documentCategoryId' => __('primary category'),
            'documentNumber' => __('document number'),
            'documentDate' => __('document date'),
            'projectName' => __('project name'),
            'projectLocation' => __('project location'),
            'approvedBudget' => __('approved budget'),
            'projectStartDate' => __('project start date'),
            'targetCompletionDate' => __('target completion date'),
            'projectRemarks' => __('project remarks'),
        ]);

        $documentYear = CarbonImmutable::parse($validated['documentDate'])->year;

        $duplicateExists = DocumentRecord::query()
            ->where('document_type_id', (int) $validated['documentTypeId'])
            ->where('document_year', $documentYear)
            ->where('document_number', $validated['documentNumber'])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'documentNumber' => __('The document number has already been used for this type and year.'),
            ]);
        }

        $categoryWasOverridden = $this->categoryWasOverridden((int) $validated['documentCategoryId']);

        $document = DB::transaction(function () use ($user, $validated, $documentYear, $categoryWasOverridden): DocumentRecord {
            $document = DocumentRecord::create([
            'document_type_id' => (int) $validated['documentTypeId'],
            'document_category_id' => (int) $validated['documentCategoryId'],
            'suggested_document_category_id' => $this->suggestedDocumentCategoryId,
            'category_suggestion_rule_id' => $this->categorySuggestionRuleId,
            'category_suggestion_terms' => $this->categorySuggestionTerms === [] ? null : $this->categorySuggestionTerms,
            'category_suggestion_reason' => $this->categorySuggestionReason,
            'category_suggested_at' => $this->suggestedDocumentCategoryId === null ? null : now(),
            'category_overridden_at' => $categoryWasOverridden ? now() : null,
            'category_overridden_by_id' => $categoryWasOverridden ? $user->id : null,
            'created_by_id' => $user->id,
            'document_number' => $validated['documentNumber'],
            'document_year' => $documentYear,
            'title' => $validated['title'],
            'document_date' => $validated['documentDate'],
            'subject' => $validated['subject'],
            'keywords' => filled($validated['keywords'] ?? null) ? $validated['keywords'] : null,
            'is_project_related' => (bool) $validated['isProjectRelated'],
            'project_name' => $validated['isProjectRelated'] && filled($validated['projectName'] ?? null) ? $validated['projectName'] : null,
            'project_location' => $validated['isProjectRelated'] && filled($validated['projectLocation'] ?? null) ? $validated['projectLocation'] : null,
            'approved_budget' => $validated['isProjectRelated'] && filled($validated['approvedBudget'] ?? null) ? $validated['approvedBudget'] : null,
            'project_start_date' => $validated['isProjectRelated'] && filled($validated['projectStartDate'] ?? null) ? $validated['projectStartDate'] : null,
            'target_completion_date' => $validated['isProjectRelated'] && filled($validated['targetCompletionDate'] ?? null) ? $validated['targetCompletionDate'] : null,
            'project_remarks' => $validated['isProjectRelated'] && filled($validated['projectRemarks'] ?? null) ? $validated['projectRemarks'] : null,
            ]);

            if ($this->suggestedDocumentCategoryId !== null) {
            $document->recordAudit($user, 'document_category_suggested', 'Document category suggested.', [
                'suggested_category_id' => $this->suggestedDocumentCategoryId,
                'rule_id' => $this->categorySuggestionRuleId,
                'matched_terms' => $this->categorySuggestionTerms,
                'reason' => $this->categorySuggestionReason,
            ]);
            }

            if ($categoryWasOverridden) {
            $document->recordAudit($user, 'document_category_overridden', $this->suggestedDocumentCategoryId === null
                ? 'Document category manually assigned because no automatic rule matched.'
                : 'Document category manually overridden.', [
                'suggested_category_id' => $this->suggestedDocumentCategoryId,
                'selected_category_id' => (int) $validated['documentCategoryId'],
                'rule_id' => $this->categorySuggestionRuleId,
                'reason' => $this->suggestedDocumentCategoryId === null ? 'no_rule_matched' : 'manual_correction',
            ]);
            }

            if ($this->residentDocumentRequestId !== null) {
            $request = ResidentDocumentRequest::query()->findOrFail($this->residentDocumentRequestId);
            abort_unless($user->can('process', $request), 404);
            app(ResidentDocumentRequestService::class)->linkDocument($user, $request, $document);
            }

            return $document;
        });

        session()->flash('status', __('Draft document record created.'));
        session()->flash('created_document_number', $document->document_number);

        return $this->residentDocumentRequestId === null
            ? redirect()->route('documents.show', $document)
            : redirect()->route('resident-document-requests.show', $this->residentDocumentRequestId);
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

    private function refreshSuggestedDocumentNumber(bool $force = false): void
    {
        if (! $force && $this->documentNumberWasEdited && filled($this->documentNumber)) {
            return;
        }

        if (! is_numeric($this->documentTypeId) || blank($this->documentDate)) {
            return;
        }

        $documentType = DocumentType::query()
            ->where('is_active', true)
            ->find((int) $this->documentTypeId);

        if (! $documentType instanceof DocumentType) {
            return;
        }

        $this->documentNumber = $documentType->nextDocumentNumber(CarbonImmutable::parse($this->documentDate)->year);
    }

    private function refreshSuggestedCategory(): void
    {
        $previousSuggestedCategoryId = $this->suggestedDocumentCategoryId;
        $suggestion = (new SuggestDocumentCategory)->handle(
            documentTypeId: is_numeric($this->documentTypeId) ? (int) $this->documentTypeId : null,
            title: $this->title,
            subject: $this->subject,
            keywords: $this->keywords,
        );

        if ($suggestion === null) {
            $wasAutomaticallyAssigned = ! $this->manualCategoryOverride
                && $previousSuggestedCategoryId !== null
                && (int) $this->documentCategoryId === $previousSuggestedCategoryId;

            $this->suggestedDocumentCategoryId = null;
            $this->categorySuggestionRuleId = null;
            $this->categorySuggestionTerms = [];
            $this->categorySuggestionReason = null;
            $this->manualCategoryOverride = true;

            if ($wasAutomaticallyAssigned) {
                $this->documentCategoryId = '';
            }

            return;
        }

        $this->suggestedDocumentCategoryId = $suggestion->category->id;
        $this->categorySuggestionRuleId = $suggestion->rule->id;
        $this->categorySuggestionTerms = $suggestion->matchedTerms;
        $this->categorySuggestionReason = $suggestion->reason;

        if (! $this->manualCategoryOverride || blank($this->documentCategoryId)) {
            $this->manualCategoryOverride = false;
            $this->documentCategoryId = (string) $suggestion->category->id;
        }
    }

    private function categoryWasOverridden(int $selectedCategoryId): bool
    {
        return $this->manualCategoryOverride
            && ($this->suggestedDocumentCategoryId === null
                || $this->suggestedDocumentCategoryId !== $selectedCategoryId);
    }
}; ?>

<section class="app-content max-w-6xl">
    <x-page-header
        :eyebrow="__('Step 1 of the document workflow')"
        :title="__('Create Document Record')"
        :description="__('Create the Draft metadata first. The permanent verification identity follows; the final PDF is uploaded later.')"
    />

    <form wire:submit="save" class="grid gap-5">
        <x-panel :title="__('Document metadata')" :description="__('Required information used to identify, retrieve, and categorize the record.')">
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Document type') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model.live="documentTypeId">
                        <flux:select.option value="">{{ __('Select document type') }}</flux:select.option>
                        @foreach ($this->documentTypes as $documentType)
                            <flux:select.option :value="$documentType->id" wire:key="document-type-{{ $documentType->id }}">{{ $documentType->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="documentTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document number') }} <span class="text-red-600">*</span></flux:label>
                    <div class="flex gap-2">
                        <flux:input wire:model.live.blur="documentNumber" placeholder="RES-2026-001" />
                        <flux:button type="button" variant="outline" icon="arrow-path" wire:click="useSuggestedDocumentNumber" wire:loading.attr="disabled" wire:target="useSuggestedDocumentNumber">
                            {{ __('Suggest') }}
                        </flux:button>
                    </div>
                    <flux:description>{{ __('Numbers are unique per document type and year. Historical records may use a manually entered number.') }}</flux:description>
                    <flux:error name="documentNumber" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Title') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model.live.blur="title" :placeholder="__('Enter the official document title')" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document date') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model.live="documentDate" type="date" />
                    <flux:error name="documentDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Primary category') }} <span class="text-red-600">*</span></flux:label>
                    @if ($suggestedDocumentCategoryId !== null && ! $manualCategoryOverride)
                        @php($suggestedCategory = $this->documentCategories->firstWhere('id', $suggestedDocumentCategoryId))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                            <p class="font-semibold">{{ __('Automatically assigned: :category', ['category' => $suggestedCategory?->name ?? __('Unknown category')]) }}</p>
                            <p class="mt-1 text-xs">{{ $categorySuggestionReason }}</p>
                            <flux:button class="mt-3" size="sm" variant="outline" type="button" wire:click="beginManualCategoryOverride">{{ __('Correct category') }}</flux:button>
                        </div>
                    @else
                        @if ($suggestedDocumentCategoryId === null)
                            <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                <p class="font-semibold">{{ __('No automatic category matched') }}</p>
                                <p class="mt-1 text-xs">{{ __('Select the most appropriate category before saving this exceptional record.') }}</p>
                            </div>
                        @else
                            @php($suggestedCategory = $this->documentCategories->firstWhere('id', $suggestedDocumentCategoryId))
                            <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                <p class="font-semibold">{{ __('Automatic result: :category', ['category' => $suggestedCategory?->name ?? __('Unknown category')]) }}</p>
                                <p class="mt-1 text-xs">{{ __('Choose a correction below, or return to the automatic result.') }}</p>
                                <flux:button class="mt-3" size="sm" variant="outline" type="button" wire:click="useSuggestedCategory">{{ __('Use automatic category') }}</flux:button>
                            </div>
                        @endif

                        <flux:select wire:model="documentCategoryId">
                            <flux:select.option value="">{{ __('Select primary category') }}</flux:select.option>
                            @foreach ($this->documentCategories as $documentCategory)
                                <flux:select.option :value="$documentCategory->id" wire:key="document-category-{{ $documentCategory->id }}">{{ $documentCategory->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:error name="documentCategoryId" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Subject or description') }} <span class="text-red-600">*</span></flux:label>
                    <flux:textarea wire:model.live.blur="subject" rows="4" :placeholder="__('Summarize the purpose and subject of this document')" />
                    <flux:error name="subject" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Keywords') }}</flux:label>
                    <flux:input wire:model.live.blur="keywords" :placeholder="__('Enter structured keywords separated by commas')" />
                    <flux:description>{{ __('Used by automatic, explainable categorization rules; PDF text is not scanned.') }}</flux:description>
                    <flux:error name="keywords" />
                </flux:field>
            </div>
        </x-panel>

        <x-panel :title="__('Project metadata')" :description="__('Optional structured fields for project-related resolutions, ordinances, or certifications.')">
            <div class="grid gap-5">
                <flux:checkbox wire:model.live="isProjectRelated" :label="__('This document is project-related')" />

                @if ($isProjectRelated)
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Project name') }}</flux:label>
                            <flux:input wire:model="projectName" :placeholder="__('Barangay drainage improvement')" />
                            <flux:error name="projectName" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Project location') }}</flux:label>
                            <flux:input wire:model="projectLocation" :placeholder="__('Purok 3, Barangay Sta. Felomina')" />
                            <flux:error name="projectLocation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Approved budget') }}</flux:label>
                            <flux:input wire:model="approvedBudget" type="number" min="0" step="0.01" />
                            <flux:error name="approvedBudget" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Project start date') }}</flux:label>
                            <flux:input wire:model="projectStartDate" type="date" />
                            <flux:error name="projectStartDate" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Target completion date') }}</flux:label>
                            <flux:input wire:model="targetCompletionDate" type="date" />
                            <flux:error name="targetCompletionDate" />
                        </flux:field>

                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Project remarks') }}</flux:label>
                            <flux:textarea wire:model="projectRemarks" rows="3" :placeholder="__('Optional project context for search and reporting')" />
                            <flux:error name="projectRemarks" />
                        </flux:field>
                    </div>
                @endif
            </div>
        </x-panel>

        <x-panel :title="__('What happens next')">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([['qr-code', 'Permanent QR identity', 'Created for this record and retained through every lifecycle state.'], ['document-arrow-up', 'Final PDF later', 'Prepare the document externally, insert its QR, then upload the PDF.'], ['shield-check', 'Captain approval', 'Only publication changes the public page to Verified.']] as [$icon, $title, $text])
                    <div class="flex gap-3" wire:key="next-step-{{ str($title)->slug() }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"><flux:icon :name="$icon" class="size-5" /></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button variant="outline" :href="route('documents.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="document-plus" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Create Draft Record') }}</span>
                <span wire:loading wire:target="save">{{ __('Creating...') }}</span>
            </flux:button>
        </div>
    </form>
</section>
