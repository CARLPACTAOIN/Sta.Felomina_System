<?php

use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Document Record')] class extends Component
{
    public string $documentTypeId = '';
    public string $documentCategoryId = '';
    public string $documentNumber = '';
    public string $title = '';
    public string $documentDate = '';
    public string $subject = '';
    public string $keywords = '';
    public bool $documentNumberWasEdited = false;

    public function mount(): void
    {
        $this->documentDate = now()->toDateString();
    }

    public function updatedDocumentTypeId(): void
    {
        $this->refreshSuggestedDocumentNumber();
    }

    public function updatedDocumentDate(): void
    {
        $this->refreshSuggestedDocumentNumber();
    }

    public function updatedDocumentNumber(): void
    {
        $this->documentNumberWasEdited = filled($this->documentNumber);
    }

    public function useSuggestedDocumentNumber(): void
    {
        $this->documentNumberWasEdited = false;
        $this->refreshSuggestedDocumentNumber(force: true);
    }

    public function save(): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role->canManageDocuments(), 403);

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
        ], attributes: [
            'documentTypeId' => __('document type'),
            'documentCategoryId' => __('primary category'),
            'documentNumber' => __('document number'),
            'documentDate' => __('document date'),
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

        $document = DocumentRecord::create([
            'document_type_id' => (int) $validated['documentTypeId'],
            'document_category_id' => (int) $validated['documentCategoryId'],
            'created_by_id' => $user->id,
            'document_number' => $validated['documentNumber'],
            'document_year' => $documentYear,
            'title' => $validated['title'],
            'document_date' => $validated['documentDate'],
            'subject' => $validated['subject'],
            'keywords' => filled($validated['keywords'] ?? null) ? $validated['keywords'] : null,
        ]);

        session()->flash('status', __('Draft document record created.'));
        session()->flash('created_document_number', $document->document_number);

        return redirect()->route('documents.index');
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
                    <flux:input wire:model="title" :placeholder="__('Enter the official document title')" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document date') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model.live="documentDate" type="date" />
                    <flux:error name="documentDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Primary category') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model="documentCategoryId">
                        <flux:select.option value="">{{ __('Select primary category') }}</flux:select.option>
                        @foreach ($this->documentCategories as $documentCategory)
                            <flux:select.option :value="$documentCategory->id" wire:key="document-category-{{ $documentCategory->id }}">{{ $documentCategory->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="documentCategoryId" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Subject or description') }} <span class="text-red-600">*</span></flux:label>
                    <flux:textarea wire:model="subject" rows="4" :placeholder="__('Summarize the purpose and subject of this document')" />
                    <flux:error name="subject" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Keywords') }}</flux:label>
                    <flux:input wire:model="keywords" :placeholder="__('Enter structured keywords separated by commas')" />
                    <flux:description>{{ __('Used later by explainable categorization rules; PDF text is not scanned.') }}</flux:description>
                    <flux:error name="keywords" />
                </flux:field>
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
