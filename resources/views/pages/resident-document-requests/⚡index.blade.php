<?php

use App\Enums\ResidentDocumentRequestStatus;
use App\Models\Purok;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Resident Document Requests')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $purokFilter = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedPurokFilter(): void { $this->resetPage(); }

    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        $user = $this->user();
        $query = ResidentDocumentRequest::query()->with(['documentType', 'purok'])->latest()->latest('id');

        if (! $user->can('resident-document-requests.view.barangay')) {
            $query->where('purok_id', $user->purok_id);
        }

        return $query
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', '%'.$this->search.'%')->orWhere('resident_name_snapshot', 'ilike', '%'.$this->search.'%')))
            ->when(filled($this->statusFilter), fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when(filled($this->purokFilter) && $user->can('resident-document-requests.view.barangay'), fn (Builder $query) => $query->where('purok_id', (int) $this->purokFilter))
            ->paginate(15);
    }

    #[Computed]
    public function puroks(): \Illuminate\Database\Eloquent\Collection
    {
        return Purok::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $this->authorize('viewAny', ResidentDocumentRequest::class);
        return $user;
    }
}; ?>

<section class="app-content max-w-7xl">
    <x-page-header :eyebrow="__('Resident services')" :title="__('Document Requests')" :description="__('Track in-person requests from Purok intake through official document release.')">
        @can('create', App\Models\ResidentDocumentRequest::class)
            <x-slot:actions><flux:button variant="primary" icon="plus" :href="route('resident-document-requests.create')" wire:navigate>{{ __('New request') }}</flux:button></x-slot:actions>
        @endcan
    </x-page-header>

    <x-panel>
        <div class="grid gap-3 md:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Reference or resident')" icon="magnifying-glass" />
            <flux:select wire:model.live="statusFilter"><flux:select.option value="">{{ __('All statuses') }}</flux:select.option>@foreach (ResidentDocumentRequestStatus::cases() as $status)<flux:select.option :value="$status->value" wire:key="status-{{ $status->value }}">{{ $status->label() }}</flux:select.option>@endforeach</flux:select>
            @if (auth()->user()?->can('resident-document-requests.view.barangay'))
                <flux:select wire:model.live="purokFilter"><flux:select.option value="">{{ __('All Puroks') }}</flux:select.option>@foreach ($this->puroks as $purok)<flux:select.option :value="$purok->id" wire:key="purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>@endforeach</flux:select>
            @endif
        </div>

        <flux:table class="mt-5" :paginate="$this->requests">
            <flux:table.columns><flux:table.column>{{ __('Reference') }}</flux:table.column><flux:table.column>{{ __('Resident') }}</flux:table.column><flux:table.column>{{ __('Type') }}</flux:table.column><flux:table.column>{{ __('Purok') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($this->requests as $request)
                    <flux:table.row :key="$request->id" :href="route('resident-document-requests.show', $request)" wire:navigate>
                        <flux:table.cell variant="strong">{{ $request->reference }}</flux:table.cell><flux:table.cell>{{ $request->resident_name_snapshot }}</flux:table.cell><flux:table.cell>{{ $request->documentType->name }}</flux:table.cell><flux:table.cell>{{ $request->purok->name }}</flux:table.cell><flux:table.cell><flux:badge>{{ $request->status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="py-10 text-center text-slate-500">{{ __('No resident document requests match the current filters.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-panel>
</section>
