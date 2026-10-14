<?php

use App\Enums\StaffPermission;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Households')] class extends Component
{
    public string $search = '';
    public string $purokFilter = '';
    public string $purokId = '';
    public string $address = '';
    public string $householdHeadName = '';
    public string $notes = '';
    public ?int $editingHouseholdId = null;
    public string $editPurokId = '';
    public string $editAddress = '';
    public string $editHouseholdHeadName = '';
    public string $editNotes = '';

    public function mount(): void
    {
        $user = $this->user();

        if ($user->isPurokChairperson() && $user->purok_id !== null) {
            $this->purokFilter = (string) $user->purok_id;
            $this->purokId = (string) $user->purok_id;
        }
    }

    public function createHousehold(): void
    {
        $actor = $this->user();
        $this->authorizeHouseholdManagement($actor);

        $validated = $this->validate($this->createRules(), attributes: $this->validationAttributes());
        $purokId = $this->authorizedPurokId($actor, $validated['purokId'] ?? null);

        $household = Household::create([
            'purok_id' => $purokId,
            'address' => $validated['address'],
            'household_head_name' => $validated['householdHeadName'],
            'notes' => $validated['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);

        AuditLog::record($actor, 'household_created', $household, 'Household created.', [
            'household_code' => $household->household_code,
            'purok_id' => $household->purok_id,
        ]);

        $this->resetCreateForm();

        Flux::toast(variant: 'success', text: __('Household created.'));
    }

    public function beginEdit(int $householdId): void
    {
        $this->user();

        $household = $this->authorizedHouseholdQuery()
            ->find($householdId);

        abort_unless($household instanceof Household, 404);

        $this->editingHouseholdId = $household->id;
        $this->editPurokId = (string) $household->purok_id;
        $this->editAddress = $household->address;
        $this->editHouseholdHeadName = $household->household_head_name;
        $this->editNotes = $household->notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingHouseholdId = null;
        $this->editPurokId = '';
        $this->editAddress = '';
        $this->editHouseholdHeadName = '';
        $this->editNotes = '';
    }

    public function updateHousehold(): void
    {
        $actor = $this->user();
        $this->authorizeHouseholdManagement($actor);

        abort_unless($this->editingHouseholdId !== null, 404);

        $household = $this->authorizedHouseholdQuery()
            ->find($this->editingHouseholdId);

        abort_unless($household instanceof Household, 404);
        $validated = $this->validate($this->updateRules(), attributes: $this->validationAttributes(prefix: 'edit'));
        $purokId = $this->authorizedPurokId($actor, $validated['editPurokId'] ?? null);

        if ($household->residents()->exists() && $household->purok_id !== $purokId) {
            throw ValidationException::withMessages([
                'editPurokId' => __('A household with residents cannot be moved to another Purok in this slice.'),
            ]);
        }

        $household->forceFill([
            'purok_id' => $purokId,
            'address' => $validated['editAddress'],
            'household_head_name' => $validated['editHouseholdHeadName'],
            'notes' => $validated['editNotes'] ?? null,
            'updated_by_id' => $actor->id,
        ])->save();

        AuditLog::record($actor, 'household_updated', $household, 'Household updated.', [
            'household_code' => $household->household_code,
            'purok_id' => $household->purok_id,
        ]);

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: __('Household updated.'));
    }

    /**
     * @return EloquentCollection<int, Household>
     */
    #[Computed]
    public function households(): EloquentCollection
    {
        return $this->authorizedHouseholdQuery()
            ->with('purok')
            ->withCount('residents')
            ->when($this->search !== '', function (Builder $query): void {
                $search = mb_strtolower($this->search);

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(household_code) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(household_head_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(address) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when($this->canViewBarangayWide() && $this->purokFilter !== '', fn (Builder $query) => $query->where('purok_id', (int) $this->purokFilter))
            ->latest()
            ->limit(50)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Purok>
     */
    #[Computed]
    public function puroks(): EloquentCollection
    {
        return Purok::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function householdCount(): int
    {
        return $this->authorizedHouseholdQuery()->count();
    }

    #[Computed]
    public function residentCount(): int
    {
        return $this->authorizedHouseholdQuery()
            ->join('residents', 'residents.household_id', '=', 'households.id')
            ->count('residents.id');
    }

    public function canViewBarangayWide(): bool
    {
        return $this->user()->canViewBarangayHouseholds();
    }

    public function canManageHouseholds(): bool
    {
        $user = $this->user();

        return $user->can(StaffPermission::ManageHouseholdsBarangay->value)
            || $user->can(StaffPermission::ManageHouseholdsAssigned->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function createRules(): array
    {
        return [
            'purokId' => [
                Rule::requiredIf($this->canViewBarangayWide()),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
            'address' => ['required', 'string', 'max:255'],
            'householdHeadName' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function updateRules(): array
    {
        return [
            'editPurokId' => [
                Rule::requiredIf($this->canViewBarangayWide()),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
            'editAddress' => ['required', 'string', 'max:255'],
            'editHouseholdHeadName' => ['required', 'string', 'max:255'],
            'editNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(string $prefix = ''): array
    {
        if ($prefix === 'edit') {
            return [
                'editPurokId' => __('purok'),
                'editAddress' => __('address'),
                'editHouseholdHeadName' => __('household head'),
                'editNotes' => __('notes'),
            ];
        }

        return [
            'purokId' => __('purok'),
            'address' => __('address'),
            'householdHeadName' => __('household head'),
            'notes' => __('notes'),
        ];
    }

    private function authorizedPurokId(User $actor, mixed $requestedPurokId): int
    {
        if ($actor->can(StaffPermission::ManageHouseholdsAssigned->value) && ! $actor->can(StaffPermission::ManageHouseholdsBarangay->value)) {
            abort_if($actor->purok_id === null, 403);

            return $actor->purok_id;
        }

        abort_unless($actor->can(StaffPermission::ManageHouseholdsBarangay->value), 403);

        return (int) $requestedPurokId;
    }

    /**
     * @return Builder<Household>
     */
    private function authorizedHouseholdQuery(): Builder
    {
        $query = Household::query();
        $user = $this->user();

        if ($user->can(StaffPermission::ViewHouseholdsAssigned->value) && ! $user->can(StaffPermission::ViewHouseholdsBarangay->value)) {
            abort_if($user->purok_id === null, 403);

            $query->where('households.purok_id', $user->purok_id);
        } else {
            abort_unless($user->can(StaffPermission::ViewHouseholdsBarangay->value), 403);
        }

        return $query;
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function authorizeHouseholdManagement(User $actor): void
    {
        abort_unless(
            $actor->can(StaffPermission::ManageHouseholdsBarangay->value)
                || $actor->can(StaffPermission::ManageHouseholdsAssigned->value),
            403,
        );
    }

    private function resetCreateForm(): void
    {
        $this->address = '';
        $this->householdHeadName = '';
        $this->notes = '';

        if ($this->canViewBarangayWide()) {
            $this->purokId = '';
        }
    }
};
?>

<div class="app-content">
    @php($isPurokChairperson = auth()->user()->isPurokChairperson())

    <x-page-header
        :eyebrow="$isPurokChairperson ? __('Assigned purok only') : __('Barangay-wide access')"
        :title="__('Households')"
        :description="__('Group residents under shared household information and reduce repeated address encoding.')"
    />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-metric-card :label="__('Households')" :value="$this->householdCount" icon="home-modern" :hint="$isPurokChairperson ? __('Assigned purok only') : __('All authorized puroks')" />
        <x-metric-card :label="__('Household residents')" :value="$this->residentCount" icon="users" :hint="__('Residents attached to listed households')" />
    </div>

    @if ($this->canManageHouseholds())
        <x-panel :title="__('New household')" :description="__('Create a shared household address record before attaching residents.')">
        <form wire:submit="createHousehold" class="grid gap-4 lg:grid-cols-2">
            @if ($this->canViewBarangayWide())
                <flux:field>
                    <flux:label>{{ __('Purok') }}</flux:label>
                    <flux:select wire:model="purokId">
                        <flux:select.option value="">{{ __('Select Purok') }}</flux:select.option>
                        @foreach ($this->puroks as $purok)
                            <flux:select.option :value="$purok->id" wire:key="create-household-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="purokId" />
                </flux:field>
            @else
                <flux:callout variant="success" icon="lock-closed" class="lg:col-span-2">
                    {{ __('New households are automatically assigned to your Purok.') }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('Household head') }}</flux:label>
                <flux:input wire:model="householdHeadName" />
                <flux:error name="householdHeadName" />
            </flux:field>

            <flux:field class="lg:col-span-2">
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <flux:field class="lg:col-span-2">
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="2" />
                <flux:error name="notes" />
            </flux:field>

            <div class="lg:col-span-2">
                <flux:button type="submit" variant="primary" icon="plus" wire:loading.attr="disabled">{{ __('Create household') }}</flux:button>
            </div>
        </form>
        </x-panel>
    @endif

    <x-panel :title="__('Household records')" :padding="false">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-700">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search household code, head, or address…')" />
            </div>
            @if ($this->canViewBarangayWide())
                <flux:select wire:model.live="purokFilter" class="sm:w-48">
                    <flux:select.option value="">{{ __('All Puroks') }}</flux:select.option>
                    @foreach ($this->puroks as $purok)
                        <flux:select.option :value="$purok->id" wire:key="filter-household-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="data-table min-w-[760px]">
                <thead>
                    <tr>
                        <th>{{ __('Household') }}</th>
                        <th>{{ __('Household head') }}</th>
                        <th>{{ __('Purok') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Members') }}</th>
                        <th><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->households as $household)
                        <tr wire:key="household-row-{{ $household->id }}">
                            <td class="font-medium">{{ $household->household_code }}</td>
                            <td>{{ $household->household_head_name }}</td>
                            <td>{{ $household->purok->name }}</td>
                            <td>{{ $household->address }}</td>
                            <td>{{ $household->residents_count }}</td>
                            <td class="text-right">
                                @if ($this->canManageHouseholds())
                                    <flux:button size="sm" variant="outline" wire:click="beginEdit({{ $household->id }})">{{ __('Edit') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :title="__('No households recorded')" :description="__('Create a household to reuse shared address information for related residents.')" icon="home-modern" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    @if ($this->canManageHouseholds() && $editingHouseholdId !== null)
        <x-panel :title="__('Edit household')" :description="__('Changes are audited. Households with residents cannot be moved to another Purok in this slice.')">
            <form wire:submit="updateHousehold" class="grid gap-4 lg:grid-cols-2">
                @if ($this->canViewBarangayWide())
                    <flux:field>
                        <flux:label>{{ __('Purok') }}</flux:label>
                        <flux:select wire:model="editPurokId">
                            <flux:select.option value="">{{ __('Select Purok') }}</flux:select.option>
                            @foreach ($this->puroks as $purok)
                                <flux:select.option :value="$purok->id" wire:key="edit-household-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editPurokId" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Household head') }}</flux:label>
                    <flux:input wire:model="editHouseholdHeadName" />
                    <flux:error name="editHouseholdHeadName" />
                </flux:field>

                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('Address') }}</flux:label>
                    <flux:input wire:model="editAddress" />
                    <flux:error name="editAddress" />
                </flux:field>

                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="editNotes" rows="2" />
                    <flux:error name="editNotes" />
                </flux:field>

                <div class="flex gap-2 lg:col-span-2">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save changes') }}</flux:button>
                    <flux:button type="button" variant="outline" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </x-panel>
    @endif
</div>
