<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\Purok;
use App\Models\User;
use App\Services\ReportEngine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Reports')] class extends Component
{
    use WithPagination;

    public string $reportType = 'document';

    // Document filters
    public string $year = '';
    public string $month = '';
    public string $categoryId = '';
    public string $typeId = '';
    public string $status = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // Resident filters
    public string $purokId = '';
    public string $residentStatus = '';
    public string $voterStatus = '';
    public string $sex = '';
    public string $civilStatus = '';

    public function mount(): void
    {
        $user = $this->user();

        if ($user->role === UserRole::PurokChairperson) {
            $this->reportType = 'resident';
            $this->purokId = (string) $user->purok_id;
        } else {
            $this->year = (string) now()->year;
        }
    }

    public function updatingReportType(): void
    {
        $this->resetPage();
    }

    public function updatedYear(): void
    {
        $this->resetPage();
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedTypeId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPurokId(): void
    {
        $this->resetPage();
    }

    public function updatedResidentStatus(): void
    {
        $this->resetPage();
    }

    public function updatedVoterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSex(): void
    {
        $this->resetPage();
    }

    public function updatedCivilStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $user = $this->user();

        if ($this->reportType === 'document') {
            $this->year = (string) now()->year;
            $this->month = '';
            $this->categoryId = '';
            $this->typeId = '';
            $this->status = '';
            $this->dateFrom = '';
            $this->dateTo = '';
        } else {
            if ($user->role !== UserRole::PurokChairperson) {
                $this->purokId = '';
            }
            $this->residentStatus = '';
            $this->voterStatus = '';
            $this->sex = '';
            $this->civilStatus = '';
        }

        $this->resetPage();
    }

    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function isPurokChairperson(): bool
    {
        return $this->user()->role === UserRole::PurokChairperson;
    }

    /**
     * @return EloquentCollection<int, DocumentCategory>
     */
    #[Computed]
    public function categories(): EloquentCollection
    {
        return DocumentCategory::query()->orderBy('name')->get();
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
     * @return EloquentCollection<int, Purok>
     */
    #[Computed]
    public function puroks(): EloquentCollection
    {
        return Purok::query()->orderBy('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function reportData(): array
    {
        $user = $this->user();
        $engine = app(ReportEngine::class);

        if ($this->reportType === 'document' && ! $this->isPurokChairperson()) {
            return $engine->documentReport($user, [
                'year' => $this->year,
                'month' => $this->month,
                'category_id' => $this->categoryId,
                'type_id' => $this->typeId,
                'status' => $this->status,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ], perPage: 15);
        }

        return $engine->residentReport($user, [
            'purok_id' => $this->purokId,
            'status' => $this->residentStatus,
            'voter_status' => $this->voterStatus,
            'sex' => $this->sex,
            'civil_status' => $this->civilStatus,
        ], perPage: 15);
    }

    public function printUrl(): string
    {
        $params = ['type' => $this->reportType];

        if ($this->reportType === 'document') {
            $params = array_merge($params, array_filter([
                'year' => $this->year,
                'month' => $this->month,
                'category_id' => $this->categoryId,
                'type_id' => $this->typeId,
                'status' => $this->status,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ], fn ($val) => $val !== ''));
        } else {
            $params = array_merge($params, array_filter([
                'purok_id' => $this->purokId,
                'status' => $this->residentStatus,
                'voter_status' => $this->voterStatus,
                'sex' => $this->sex,
                'civil_status' => $this->civilStatus,
            ], fn ($val) => $val !== ''));
        }

        return route('reports.print', $params);
    }

    public function exportPdfUrl(): string
    {
        return $this->printUrl().(str_contains($this->printUrl(), '?') ? '&' : '?').'auto_print=1';
    }
}; ?>

<div class="app-content">
    <x-page-header
        :title="$this->isPurokChairperson() ? __('Purok Summary Report') : __('Reports')"
        :description="$this->isPurokChairperson()
            ? __('Generate printable resident and household summaries for your assigned purok.')
            : __('One reusable filter-based reporting engine for official documents and resident profiling.')"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5">
                <flux:button variant="outline" icon="arrow-path" wire:click="resetFilters">
                    {{ __('Reset filters') }}
                </flux:button>
                <flux:button variant="outline" icon="printer" :href="$this->printUrl()" target="_blank">
                    {{ __('Print report') }}
                </flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" :href="$this->exportPdfUrl()" target="_blank">
                    {{ __('Export PDF') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (! $this->isPurokChairperson())
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button
                type="button"
                wire:click="$set('reportType', 'document')"
                class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $reportType === 'document' ? 'border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-300' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}"
            >
                <flux:icon name="document-text" class="size-4" />
                {{ __('Document Report') }}
            </button>
            <button
                type="button"
                wire:click="$set('reportType', 'resident')"
                class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $reportType === 'resident' ? 'border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-300' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}"
            >
                <flux:icon name="users" class="size-4" />
                {{ __('Resident Report') }}
            </button>
        </div>
    @endif

    <x-panel :title="__('Filter Criteria')" :description="__('Configure the parameters for your generated report.')">
        @if ($reportType === 'document' && ! $this->isPurokChairperson())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('Year') }}</flux:label>
                    <flux:select wire:model.live="year">
                        <flux:select.option value="">{{ __('All Years') }}</flux:select.option>
                        @for ($y = now()->year + 1; $y >= 2020; $y--)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endfor
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Month') }}</flux:label>
                    <flux:select wire:model.live="month">
                        <flux:select.option value="">{{ __('All Months') }}</flux:select.option>
                        @for ($m = 1; $m <= 12; $m++)
                            <flux:select.option value="{{ $m }}">{{ Carbon\Carbon::create(2026, $m, 1)->format('F') }}</flux:select.option>
                        @endfor
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Category') }}</flux:label>
                    <flux:select wire:model.live="categoryId">
                        <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document Type') }}</flux:label>
                    <flux:select wire:model.live="typeId">
                        <flux:select.option value="">{{ __('All Document Types') }}</flux:select.option>
                        @foreach ($this->types as $t)
                            <flux:select.option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model.live="status">
                        <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                        @foreach (DocumentStatus::cases() as $case)
                            <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date From') }}</flux:label>
                    <flux:input type="date" wire:model.live.debounce.300ms="dateFrom" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date To') }}</flux:label>
                    <flux:input type="date" wire:model.live.debounce.300ms="dateTo" />
                </flux:field>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if (! $this->isPurokChairperson())
                    <flux:field>
                        <flux:label>{{ __('Purok') }}</flux:label>
                        <flux:select wire:model.live="purokId">
                            <flux:select.option value="">{{ __('Barangay-wide (All Puroks)') }}</flux:select.option>
                            @foreach ($this->puroks as $purok)
                                <flux:select.option value="{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                @else
                    <flux:field>
                        <flux:label>{{ __('Assigned Scope') }}</flux:label>
                        <flux:input :value="$this->user()->purok?->name ?? __('Assigned Purok')" disabled />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Resident Status') }}</flux:label>
                    <flux:select wire:model.live="residentStatus">
                        <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                        @foreach (ResidentStatus::cases() as $case)
                            <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Voter Status') }}</flux:label>
                    <flux:select wire:model.live="voterStatus">
                        <flux:select.option value="">{{ __('All Residents') }}</flux:select.option>
                        <flux:select.option value="voter">{{ __('Registered Voters Only') }}</flux:select.option>
                        <flux:select.option value="non_voter">{{ __('Non-Voters Only') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Sex') }}</flux:label>
                    <flux:select wire:model.live="sex">
                        <flux:select.option value="">{{ __('All') }}</flux:select.option>
                        <flux:select.option value="Male">{{ __('Male') }}</flux:select.option>
                        <flux:select.option value="Female">{{ __('Female') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Civil Status') }}</flux:label>
                    <flux:select wire:model.live="civilStatus">
                        <flux:select.option value="">{{ __('All Civil Statuses') }}</flux:select.option>
                        @foreach (['Single', 'Married', 'Widowed', 'Separated'] as $cs)
                            <flux:select.option value="{{ $cs }}">{{ $cs }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        @endif
    </x-panel>

    {{-- Summary Metrics Bar --}}
    @php($data = $this->reportData)
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @if ($reportType === 'document' && ! $this->isPurokChairperson())
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Records in Report') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $data['total_count'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Dominant Category') }}</p>
                <p class="mt-1 text-lg font-bold text-emerald-800 dark:text-emerald-300">{{ $data['dominant_category'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Published Documents') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $data['status_counts']['published'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Coverage Period') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $data['period_label'] }}</p>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Residents in Report') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $data['total_count'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Active Residents') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-300">{{ $data['active_count'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Registered Voters') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $data['voter_count'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Households Represented') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $data['household_count'] }}</p>
            </div>
        @endif
    </div>

    {{-- Results Table --}}
    <x-panel :padding="false">
        @if ($reportType === 'document' && ! $this->isPurokChairperson())
            @if ($data['records']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('Document Number') }}</th>
                                <th class="px-5 py-3.5">{{ __('Title / Subject') }}</th>
                                <th class="px-5 py-3.5">{{ __('Type') }}</th>
                                <th class="px-5 py-3.5">{{ __('Category') }}</th>
                                <th class="px-5 py-3.5">{{ __('Document Date') }}</th>
                                <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['records'] as $doc)
                                <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" wire:key="doc-row-{{ $doc->id }}">
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono font-semibold text-slate-900 dark:text-white">
                                        <a href="{{ route('documents.show', $doc) }}" class="hover:underline text-emerald-800 dark:text-emerald-300" wire:navigate>
                                            {{ $doc->document_number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $doc->title }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">{{ $doc->subject }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">{{ $doc->type->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">{{ $doc->category->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">{{ $doc->document_date->format('M d, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-status-badge :status="$doc->status" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                    {{ $data['records']->links() }}
                </div>
            @else
                <x-empty-state
                    :title="__('No matching documents')"
                    :description="__('Adjust your filter criteria above to display document records in the report.')"
                    icon="document-text"
                />
            @endif
        @else
            @if ($data['records']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('Resident Code') }}</th>
                                <th class="px-5 py-3.5">{{ __('Full Name') }}</th>
                                <th class="px-5 py-3.5">{{ __('Purok') }}</th>
                                <th class="px-5 py-3.5">{{ __('Household') }}</th>
                                <th class="px-5 py-3.5">{{ __('Sex / Age') }}</th>
                                <th class="px-5 py-3.5">{{ __('Voter') }}</th>
                                <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['records'] as $resident)
                                <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" wire:key="res-row-{{ $resident->id }}">
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono font-semibold text-slate-900 dark:text-white">
                                        {{ $resident->resident_code }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-900 dark:text-white">
                                        {{ $resident->last_name }}, {{ $resident->first_name }} {{ $resident->middle_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">{{ $resident->purok->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs">
                                        {{ $resident->household->household_code }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        {{ $resident->sex }} • {{ $resident->birthdate->age }}y
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        @if ($resident->is_registered_voter)
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                {{ __('Voter') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                {{ __('Non-Voter') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <x-status-badge :status="$resident->status" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                    {{ $data['records']->links() }}
                </div>
            @else
                <x-empty-state
                    :title="__('No matching residents')"
                    :description="__('Adjust your filter criteria above to display resident records in the report.')"
                    icon="users"
                />
            @endif
        @endif
    </x-panel>
</div>
