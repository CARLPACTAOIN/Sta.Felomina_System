<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pending Approvals')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $typeId = '';
    #[Url]
    public string $categoryId = '';
    #[Url]
    public string $sort = 'newest';

    /** @var array<int, string> */
    public array $returnComments = [];

    public function mount(): void
    {
        $this->authorize('review', DocumentRecord::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeId(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeId = '';
        $this->categoryId = '';
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function approveDocument(int $documentRecordId): void
    {
        $document = $this->pendingDocument($documentRecordId);

        $this->authorize('approve', $document);

        $document->approveForPublication($this->user());
        unset($this->returnComments[$documentRecordId]);

        Flux::toast(variant: 'success', text: __('Document approved and published.'));
    }

    public function returnDocument(int $documentRecordId): void
    {
        $document = $this->pendingDocument($documentRecordId);

        $this->authorize('returnForRevision', $document);

        $this->validate([
            "returnComments.{$documentRecordId}" => ['required', 'string', 'max:2000'],
        ], attributes: [
            "returnComments.{$documentRecordId}" => __('return comment'),
        ]);

        $document->returnForRevision($this->user(), $this->returnComments[$documentRecordId]);
        unset($this->returnComments[$documentRecordId]);

        Flux::toast(variant: 'success', text: __('Document returned for revision.'));
    }

    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * @return EloquentCollection<int, DocumentType>
     */
    #[Computed]
    public function types(): EloquentCollection
    {
        return DocumentType::query()->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, DocumentCategory>
     */
    #[Computed]
    public function categories(): EloquentCollection
    {
        return DocumentCategory::query()->orderBy('name')->get();
    }

    #[Computed]
    public function pendingTotal(): int
    {
        return $this->baseQuery()->count();
    }

    #[Computed]
    public function missingReviewAssetsCount(): int
    {
        return $this->baseQuery()
            ->whereNull('draft_pdf_path')
            ->count();
    }

    #[Computed]
    public function oldestWaitingDocument(): ?DocumentRecord
    {
        return $this->baseQuery()
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, DocumentRecord>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->with(['type', 'category', 'creator', 'submitter', 'draftPdfUploader']);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('document_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%");
            });
        }

        if (filled($this->typeId)) {
            $query->where('document_type_id', (int) $this->typeId);
        }

        if (filled($this->categoryId)) {
            $query->where('document_category_id', (int) $this->categoryId);
        }

        match ($this->sort) {
            'oldest' => $query->orderBy('submitted_at')->orderBy('id'),
            'document_date' => $query->orderByDesc('document_date')->orderByDesc('id'),
            default => $query->latest('submitted_at')->latest('id'),
        };

        return $query->paginate(10);
    }

    private function pendingDocument(int $documentRecordId): DocumentRecord
    {
        return DocumentRecord::query()
            ->whereKey($documentRecordId)
            ->firstOrFail();
    }

    /**
     * @return Builder<DocumentRecord>
     */
    private function baseQuery(): Builder
    {
        return DocumentRecord::query()
            ->where('status', DocumentStatus::PendingApproval->value);
    }
}; ?>

@php
    $documents = $this->documents;
    $oldestWaitingDocument = $this->oldestWaitingDocument;
@endphp

<section class="app-content">
    <x-page-header
        :title="__('Pending Approvals')"
        :description="__('Captain review queue for submitted documents awaiting publication or revision.')"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters">{{ __('Reset filters') }}</flux:button>
                <flux:button variant="primary" icon="document-text" :href="route('documents.index')" wire:navigate>{{ __('Open archive') }}</flux:button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-[0_8px_28px_rgba(15,61,44,0.04)] dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Total pending') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $this->pendingTotal }}</p>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Submitted for Captain review') }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-[0_8px_28px_rgba(15,61,44,0.04)] dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Oldest waiting') }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">
                {{ $oldestWaitingDocument?->submitted_at?->diffForHumans() ?? __('None') }}
            </p>
            <p class="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">
                {{ $oldestWaitingDocument?->document_number ?? __('No documents awaiting review') }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-[0_8px_28px_rgba(15,61,44,0.04)] dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Missing review assets') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $this->missingReviewAssetsCount }}</p>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Pending records without a Draft PDF path') }}</p>
        </div>
    </div>

    <x-panel :title="__('Review filters')" :description="__('Find the next record by number, metadata, type, category, or waiting order.')">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_220px_220px]">
            <flux:field>
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :placeholder="__('Number, title, subject, or keyword')" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model.live="typeId">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach ($this->types as $type)
                        <flux:select.option :value="$type->id" wire:key="approval-type-{{ $type->id }}">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Category') }}</flux:label>
                <flux:select wire:model.live="categoryId">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="$category->id" wire:key="approval-category-{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Sort') }}</flux:label>
                <flux:select wire:model.live="sort">
                    <flux:select.option value="newest">{{ __('Newest submitted') }}</flux:select.option>
                    <flux:select.option value="oldest">{{ __('Oldest submitted') }}</flux:select.option>
                    <flux:select.option value="document_date">{{ __('Document date') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </x-panel>

    <x-panel :title="__('Captain review queue')" :description="__('Approve publication or return records with clear revision instructions.')" :padding="false">
        @if ($documents->isEmpty())
            <x-empty-state
                :title="__('Nothing awaiting review')"
                :description="__('Submitted documents will appear here after staff submit Draft or Needs Revision records for Captain approval.')"
                icon="inbox-arrow-down"
            />
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($documents as $document)
                    @php
                        $canApprove = $this->user()->can('approve', $document);
                        $canReturn = $this->user()->can('returnForRevision', $document);
                    @endphp

                    <article
                        id="pending-approval-{{ $document->id }}"
                        class="grid scroll-mt-24 gap-5 px-5 py-5 xl:grid-cols-[minmax(0,1fr)_360px]"
                        wire:key="pending-approval-{{ $document->id }}"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$document->status" />
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $document->type->name }}</span>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">{{ $document->category->name }}</span>
                                @if ($document->return_comment)
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800 dark:bg-red-950/50 dark:text-red-300">{{ __('Returned before') }}</span>
                                @endif
                            </div>

                            <h2 class="mt-3 text-base font-semibold text-slate-950 dark:text-white">{{ $document->document_number }} · {{ $document->title }}</h2>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $document->subject }}</p>

                            <dl class="mt-4 grid gap-3 text-xs text-slate-500 sm:grid-cols-2 lg:grid-cols-4 dark:text-slate-400">
                                <div>
                                    <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('Submitted') }}</dt>
                                    <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $document->submitted_at?->toDayDateTimeString() ?? __('Not recorded') }}</dd>
                                    <dd class="mt-0.5">{{ $document->submitted_at?->diffForHumans() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('Submitted by') }}</dt>
                                    <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $document->submitter?->name ?? __('Unknown staff') }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('Created by') }}</dt>
                                    <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $document->creator->name }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ __('Draft PDF') }}</dt>
                                    <dd class="mt-1 text-slate-700 dark:text-slate-200">
                                        {{ $document->hasDraftPdf() ? ($document->draft_pdf_original_name ?? __('Attached')) : __('Missing') }}
                                    </dd>
                                    @if ($document->draftPdfUploader)
                                        <dd class="mt-0.5">{{ __('Uploaded by :name', ['name' => $document->draftPdfUploader->name]) }}</dd>
                                    @endif
                                </div>
                            </dl>

                            @if ($document->is_project_related && (filled($document->project_name) || filled($document->project_location)))
                                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Project metadata') }}</p>
                                    <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $document->project_name ?? __('Unnamed project') }}</p>
                                    @if ($document->project_location)
                                        <p class="mt-0.5 text-xs">{{ $document->project_location }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($document->return_comment)
                                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">
                                    <p class="text-xs font-bold uppercase tracking-wider">{{ __('Prior return comment') }}</p>
                                    <p class="mt-1 leading-6">{{ $document->return_comment }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="grid content-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                <flux:button variant="outline" icon="eye" :href="route('documents.show', $document)" wire:navigate>{{ __('Open full review') }}</flux:button>
                                @if ($document->hasDraftPdf())
                                    <flux:button variant="outline" icon="document-text" :href="route('documents.draft-pdf', $document)" target="_blank">{{ __('Review Draft PDF') }}</flux:button>
                                @else
                                    <x-foundation-notice :title="__('Draft PDF missing')">{{ __('Approval is locked until a Draft PDF is attached.') }}</x-foundation-notice>
                                @endif
                            </div>

                            @if ($canReturn)
                                <form wire:submit="returnDocument({{ $document->id }})" class="grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                                    <flux:field>
                                        <flux:label>{{ __('Return comment') }}</flux:label>
                                        <flux:textarea wire:model="returnComments.{{ $document->id }}" rows="3" :placeholder="__('Explain what must be corrected before approval.')" />
                                        <flux:error name="returnComments.{{ $document->id }}" />
                                    </flux:field>
                                    <flux:button variant="outline" type="submit" icon="arrow-uturn-left" wire:loading.attr="disabled" wire:target="returnDocument({{ $document->id }})">
                                        {{ __('Return for revision') }}
                                    </flux:button>
                                </form>
                            @endif

                            @if ($canApprove)
                                <flux:button variant="primary" icon="shield-check" wire:click="approveDocument({{ $document->id }})" wire:confirm="{{ __('Approve and publish this document? The current Draft PDF will be preserved as the official public version.') }}" wire:loading.attr="disabled" wire:target="approveDocument({{ $document->id }})">
                                    {{ __('Approve and Publish') }}
                                </flux:button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                {{ $documents->links() }}
            </div>
        @endif
    </x-panel>
</section>
