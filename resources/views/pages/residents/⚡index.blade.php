<?php

use App\Enums\ResidentStatus;
use App\Enums\StaffPermission;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Residents')] class extends Component
{
    public string $search = '';
    public string $purokFilter = '';
    public string $statusFilter = '';
    public string $voterFilter = '';
    public string $firstName = '';
    public string $middleName = '';
    public string $lastName = '';
    public string $suffix = '';
    public string $sex = '';
    public string $birthdate = '';
    public string $civilStatus = '';
    public string $contactNumber = '';
    public string $address = '';
    public string $purokId = '';
    public string $householdId = '';
    public bool $isRegisteredVoter = false;
    public string $status = '';
    public string $birthplace = '';
    public string $occupation = '';
    public string $remarks = '';
    /** @var array<int, int> */
    public array $duplicateResidentIds = [];
    public ?int $editingResidentId = null;
    public string $editFirstName = '';
    public string $editMiddleName = '';
    public string $editLastName = '';
    public string $editSuffix = '';
    public string $editSex = '';
    public string $editBirthdate = '';
    public string $editCivilStatus = '';
    public string $editContactNumber = '';
    public string $editAddress = '';
    public string $editPurokId = '';
    public string $editHouseholdId = '';
    public bool $editIsRegisteredVoter = false;
    public string $editStatus = '';
    public string $editBirthplace = '';
    public string $editOccupation = '';
    public string $editRemarks = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->status = ResidentStatus::Active->value;

        if ($user->isPurokChairperson() && $user->purok_id !== null) {
            $this->purokFilter = (string) $user->purok_id;
            $this->purokId = (string) $user->purok_id;
        }
    }

    public function createResident(bool $force = false): void
    {
        $actor = $this->user();
        $this->authorizeResidentManagement($actor);

        $validated = $this->validate($this->createRules(), attributes: $this->validationAttributes());
        $purokId = $this->authorizedPurokId($actor, $validated['purokId'] ?? null);
        $household = $this->authorizedHouseholdFor((int) $validated['householdId'], $purokId);
        $normalizedFullName = Resident::normalizeFullName(
            $validated['firstName'],
            $validated['middleName'] ?? null,
            $validated['lastName'],
            $validated['suffix'] ?? null,
        );

        if (! $force) {
            $duplicates = Resident::query()
                ->likelyDuplicate($normalizedFullName, $validated['birthdate'], $purokId)
                ->with('purok')
                ->limit(5)
                ->get();

            if ($duplicates->isNotEmpty()) {
                $this->duplicateResidentIds = $duplicates->pluck('id')->all();

                return;
            }
        }

        $resident = Resident::create([
            'first_name' => $validated['firstName'],
            'middle_name' => $validated['middleName'] ?? null,
            'last_name' => $validated['lastName'],
            'suffix' => $validated['suffix'] ?? null,
            'sex' => $validated['sex'],
            'birthdate' => $validated['birthdate'],
            'civil_status' => $validated['civilStatus'],
            'contact_number' => $validated['contactNumber'] ?? null,
            'address' => $validated['address'],
            'purok_id' => $purokId,
            'household_id' => $household->id,
            'is_registered_voter' => (bool) ($validated['isRegisteredVoter'] ?? false),
            'status' => ResidentStatus::from($validated['status']),
            'birthplace' => $validated['birthplace'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'created_by_id' => $actor->id,
        ]);

        AuditLog::record($actor, 'resident_created', $resident, 'Resident record created.', [
            'resident_code' => $resident->resident_code,
            'purok_id' => $resident->purok_id,
            'household_id' => $resident->household_id,
            'duplicate_override' => $force && $this->duplicateResidentIds !== [],
        ]);

        $this->resetCreateForm();

        Flux::toast(variant: 'success', text: __('Resident created.'));
    }

    public function beginEdit(int $residentId): void
    {
        $this->user();

        $resident = $this->authorizedResidentQuery()
            ->find($residentId);

        abort_unless($resident instanceof Resident, 404);

        $this->editingResidentId = $resident->id;
        $this->editFirstName = $resident->first_name;
        $this->editMiddleName = $resident->middle_name ?? '';
        $this->editLastName = $resident->last_name;
        $this->editSuffix = $resident->suffix ?? '';
        $this->editSex = $resident->sex;
        $this->editBirthdate = $resident->birthdate->toDateString();
        $this->editCivilStatus = $resident->civil_status;
        $this->editContactNumber = $resident->contact_number ?? '';
        $this->editAddress = $resident->address;
        $this->editPurokId = (string) $resident->purok_id;
        $this->editHouseholdId = (string) $resident->household_id;
        $this->editIsRegisteredVoter = $resident->is_registered_voter;
        $this->editStatus = $resident->status->value;
        $this->editBirthplace = $resident->birthplace ?? '';
        $this->editOccupation = $resident->occupation ?? '';
        $this->editRemarks = $resident->remarks ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingResidentId = null;
        $this->editFirstName = '';
        $this->editMiddleName = '';
        $this->editLastName = '';
        $this->editSuffix = '';
        $this->editSex = '';
        $this->editBirthdate = '';
        $this->editCivilStatus = '';
        $this->editContactNumber = '';
        $this->editAddress = '';
        $this->editPurokId = '';
        $this->editHouseholdId = '';
        $this->editIsRegisteredVoter = false;
        $this->editStatus = '';
        $this->editBirthplace = '';
        $this->editOccupation = '';
        $this->editRemarks = '';
    }

    public function updateResident(): void
    {
        $actor = $this->user();
        $this->authorizeResidentManagement($actor);

        abort_unless($this->editingResidentId !== null, 404);

        $resident = $this->authorizedResidentQuery()
            ->find($this->editingResidentId);

        abort_unless($resident instanceof Resident, 404);
        $validated = $this->validate($this->updateRules(), attributes: $this->validationAttributes(prefix: 'edit'));
        $purokId = $this->authorizedPurokId($actor, $validated['editPurokId'] ?? null);
        $household = $this->authorizedHouseholdFor((int) $validated['editHouseholdId'], $purokId);
        $originalStatus = $resident->status;
        $nextStatus = ResidentStatus::from($validated['editStatus']);

        $resident->forceFill([
            'first_name' => $validated['editFirstName'],
            'middle_name' => $validated['editMiddleName'] ?? null,
            'last_name' => $validated['editLastName'],
            'suffix' => $validated['editSuffix'] ?? null,
            'sex' => $validated['editSex'],
            'birthdate' => $validated['editBirthdate'],
            'civil_status' => $validated['editCivilStatus'],
            'contact_number' => $validated['editContactNumber'] ?? null,
            'address' => $validated['editAddress'],
            'purok_id' => $purokId,
            'household_id' => $household->id,
            'is_registered_voter' => (bool) ($validated['editIsRegisteredVoter'] ?? false),
            'status' => $nextStatus,
            'birthplace' => $validated['editBirthplace'] ?? null,
            'occupation' => $validated['editOccupation'] ?? null,
            'remarks' => $validated['editRemarks'] ?? null,
            'updated_by_id' => $actor->id,
        ])->save();

        if ($originalStatus !== $nextStatus) {
            AuditLog::record($actor, 'resident_status_changed', $resident, 'Resident status changed.', [
                'from' => $originalStatus->value,
                'to' => $nextStatus->value,
            ]);
        }

        AuditLog::record($actor, 'resident_updated', $resident, 'Resident record updated.', [
            'resident_code' => $resident->resident_code,
            'purok_id' => $resident->purok_id,
            'household_id' => $resident->household_id,
            'status' => $resident->status->value,
        ]);

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: __('Resident updated.'));
    }

    /**
     * @return EloquentCollection<int, Resident>
     */
    #[Computed]
    public function residents(): EloquentCollection
    {
        return $this->authorizedResidentQuery()
            ->with(['purok', 'household'])
            ->when($this->search !== '', function (Builder $query): void {
                $search = mb_strtolower($this->search);

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(resident_code) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(normalized_full_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(middle_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(contact_number) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(address) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('household', fn (Builder $query) => $query->whereRaw('LOWER(household_code) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->when($this->canViewBarangayWide() && $this->purokFilter !== '', fn (Builder $query) => $query->where('purok_id', (int) $this->purokFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when(in_array($this->voterFilter, ['registered', 'not_registered'], true), fn (Builder $query) => $query->where('is_registered_voter', $this->voterFilter === 'registered'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Resident>
     */
    #[Computed]
    public function duplicateResidents(): EloquentCollection
    {
        if ($this->duplicateResidentIds === []) {
            return new EloquentCollection;
        }

        return Resident::query()
            ->with(['purok', 'household'])
            ->whereIn('id', $this->duplicateResidentIds)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Household>
     */
    #[Computed]
    public function households(): EloquentCollection
    {
        return $this->authorizedHouseholdQuery()
            ->with('purok')
            ->orderBy('household_head_name')
            ->orderBy('household_code')
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

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function residentStatuses(): array
    {
        return collect(ResidentStatus::cases())
            ->mapWithKeys(fn (ResidentStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    #[Computed]
    public function residentCount(): int
    {
        return $this->authorizedResidentQuery()->count();
    }

    #[Computed]
    public function activeResidentCount(): int
    {
        return $this->authorizedResidentQuery()
            ->where('status', ResidentStatus::Active)
            ->count();
    }

    #[Computed]
    public function householdCount(): int
    {
        return $this->authorizedHouseholdQuery()->count();
    }

    public function canViewBarangayWide(): bool
    {
        return $this->user()->canViewBarangayResidents();
    }

    public function canManageResidents(): bool
    {
        $user = $this->user();

        return $user->can(StaffPermission::ManageResidentsBarangay->value)
            || $user->can(StaffPermission::ManageResidentsAssigned->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function createRules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'sex' => ['required', 'string', Rule::in(['male', 'female'])],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'civilStatus' => ['required', 'string', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'contactNumber' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'purokId' => [
                Rule::requiredIf($this->canViewBarangayWide()),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
            'householdId' => ['required', 'integer', Rule::exists('households', 'id')],
            'isRegisteredVoter' => ['boolean'],
            'status' => ['required', 'string', Rule::in(array_column(ResidentStatus::cases(), 'value'))],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function updateRules(): array
    {
        return [
            'editFirstName' => ['required', 'string', 'max:255'],
            'editMiddleName' => ['nullable', 'string', 'max:255'],
            'editLastName' => ['required', 'string', 'max:255'],
            'editSuffix' => ['nullable', 'string', 'max:50'],
            'editSex' => ['required', 'string', Rule::in(['male', 'female'])],
            'editBirthdate' => ['required', 'date', 'before_or_equal:today'],
            'editCivilStatus' => ['required', 'string', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'editContactNumber' => ['nullable', 'string', 'max:50'],
            'editAddress' => ['required', 'string', 'max:255'],
            'editPurokId' => [
                Rule::requiredIf($this->canViewBarangayWide()),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
            'editHouseholdId' => ['required', 'integer', Rule::exists('households', 'id')],
            'editIsRegisteredVoter' => ['boolean'],
            'editStatus' => ['required', 'string', Rule::in(array_column(ResidentStatus::cases(), 'value'))],
            'editBirthplace' => ['nullable', 'string', 'max:255'],
            'editOccupation' => ['nullable', 'string', 'max:255'],
            'editRemarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(string $prefix = ''): array
    {
        $base = [
            'firstName' => __('first name'),
            'middleName' => __('middle name'),
            'lastName' => __('last name'),
            'suffix' => __('suffix'),
            'sex' => __('sex'),
            'birthdate' => __('birthdate'),
            'civilStatus' => __('civil status'),
            'contactNumber' => __('contact number'),
            'address' => __('address'),
            'purokId' => __('purok'),
            'householdId' => __('household'),
            'isRegisteredVoter' => __('voter status'),
            'status' => __('resident status'),
            'birthplace' => __('birthplace'),
            'occupation' => __('occupation'),
            'remarks' => __('remarks'),
        ];

        if ($prefix !== 'edit') {
            return $base;
        }

        return collect($base)
            ->mapWithKeys(fn (string $label, string $key): array => ['edit'.ucfirst($key) => $label])
            ->all();
    }

    private function authorizedPurokId(User $actor, mixed $requestedPurokId): int
    {
        if ($actor->can(StaffPermission::ManageResidentsAssigned->value) && ! $actor->can(StaffPermission::ManageResidentsBarangay->value)) {
            abort_if($actor->purok_id === null, 403);

            return $actor->purok_id;
        }

        abort_unless($actor->can(StaffPermission::ManageResidentsBarangay->value), 403);

        return (int) $requestedPurokId;
    }

    private function authorizedHouseholdFor(int $householdId, int $purokId): Household
    {
        $household = $this->authorizedHouseholdQuery()
            ->where('purok_id', $purokId)
            ->find($householdId);

        if (! $household instanceof Household) {
            throw ValidationException::withMessages([
                ($this->editingResidentId === null ? 'householdId' : 'editHouseholdId') => __('The selected household must belong to the resident Purok.'),
            ]);
        }

        return $household;
    }

    /**
     * @return Builder<Resident>
     */
    private function authorizedResidentQuery(): Builder
    {
        $query = Resident::query();
        $user = $this->user();

        if ($user->can(StaffPermission::ViewResidentsAssigned->value) && ! $user->can(StaffPermission::ViewResidentsBarangay->value)) {
            abort_if($user->purok_id === null, 403);

            $query->where('residents.purok_id', $user->purok_id);
        } else {
            abort_unless($user->can(StaffPermission::ViewResidentsBarangay->value), 403);
        }

        return $query;
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

            $query->where('purok_id', $user->purok_id);
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

    private function authorizeResidentManagement(User $actor): void
    {
        abort_unless(
            $actor->can(StaffPermission::ManageResidentsBarangay->value)
                || $actor->can(StaffPermission::ManageResidentsAssigned->value),
            403,
        );
    }

    private function resetCreateForm(): void
    {
        $this->firstName = '';
        $this->middleName = '';
        $this->lastName = '';
        $this->suffix = '';
        $this->sex = '';
        $this->birthdate = '';
        $this->civilStatus = '';
        $this->contactNumber = '';
        $this->address = '';
        $this->householdId = '';
        $this->isRegisteredVoter = false;
        $this->status = ResidentStatus::Active->value;
        $this->birthplace = '';
        $this->occupation = '';
        $this->remarks = '';
        $this->duplicateResidentIds = [];

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
        :title="$isPurokChairperson ? __('Purok Residents') : __('Resident Directory')"
        :description="$isPurokChairperson ? __('Search and maintain resident profiles within your assigned purok.') : __('Search resident profiles across all puroks according to your role.')"
    />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-metric-card :label="__('Residents')" :value="$this->residentCount" icon="users" :hint="$isPurokChairperson ? __('Assigned purok only') : __('All authorized puroks')" />
        <x-metric-card :label="__('Active')" :value="$this->activeResidentCount" icon="user-group" :hint="__('Current resident status')" />
        <x-metric-card :label="__('Households')" :value="$this->householdCount" icon="home-modern" :hint="__('Shared address records')" />
    </div>

    @if ($this->canManageResidents())
        <x-panel :title="__('New resident')" :description="__('Search first, then create a profile attached to a household.')">
        @if ($this->duplicateResidents->isNotEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                <div class="space-y-3">
                    <p>{{ __('Possible duplicate resident records were found. Review them before saving.') }}</p>
                    <ul class="list-disc space-y-1 ps-5 text-sm">
                        @foreach ($this->duplicateResidents as $duplicate)
                            <li wire:key="duplicate-resident-{{ $duplicate->id }}">
                                {{ $duplicate->resident_code }} — {{ $duplicate->fullName() }} — {{ $duplicate->birthdate->toDateString() }} — {{ $duplicate->purok->name }}
                            </li>
                        @endforeach
                    </ul>
                    <flux:button type="button" variant="primary" wire:click="createResident(true)" wire:loading.attr="disabled">{{ __('Save anyway') }}</flux:button>
                </div>
            </flux:callout>
        @endif

        <form wire:submit="createResident" class="grid gap-4 lg:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('First name') }}</flux:label>
                <flux:input wire:model="firstName" />
                <flux:error name="firstName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Middle name') }}</flux:label>
                <flux:input wire:model="middleName" />
                <flux:error name="middleName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Last name') }}</flux:label>
                <flux:input wire:model="lastName" />
                <flux:error name="lastName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Suffix') }}</flux:label>
                <flux:input wire:model="suffix" />
                <flux:error name="suffix" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Sex') }}</flux:label>
                <flux:select wire:model="sex">
                    <flux:select.option value="">{{ __('Select sex') }}</flux:select.option>
                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                </flux:select>
                <flux:error name="sex" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Birthdate') }}</flux:label>
                <flux:input type="date" wire:model="birthdate" />
                <flux:error name="birthdate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Civil status') }}</flux:label>
                <flux:select wire:model="civilStatus">
                    <flux:select.option value="">{{ __('Select status') }}</flux:select.option>
                    <flux:select.option value="single">{{ __('Single') }}</flux:select.option>
                    <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                    <flux:select.option value="widowed">{{ __('Widowed') }}</flux:select.option>
                    <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                </flux:select>
                <flux:error name="civilStatus" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Contact number') }}</flux:label>
                <flux:input wire:model="contactNumber" />
                <flux:error name="contactNumber" />
            </flux:field>

            @if ($this->canViewBarangayWide())
                <flux:field>
                    <flux:label>{{ __('Purok') }}</flux:label>
                    <flux:select wire:model.live="purokId">
                        <flux:select.option value="">{{ __('Select Purok') }}</flux:select.option>
                        @foreach ($this->puroks as $purok)
                            <flux:select.option :value="$purok->id" wire:key="create-resident-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="purokId" />
                </flux:field>
            @else
                <flux:callout variant="success" icon="lock-closed">
                    {{ __('New residents are automatically assigned to your Purok.') }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('Household') }}</flux:label>
                <flux:select wire:model="householdId">
                    <flux:select.option value="">{{ __('Select household') }}</flux:select.option>
                    @foreach ($this->households as $household)
                        @if (! $this->canViewBarangayWide() || $purokId === '' || $household->purok_id === (int) $purokId)
                            <flux:select.option :value="$household->id" wire:key="create-resident-household-{{ $household->id }}">{{ $household->household_code }} — {{ $household->household_head_name }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:error name="householdId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Resident status') }}</flux:label>
                <flux:select wire:model="status">
                    @foreach ($this->residentStatuses as $value => $label)
                        <flux:select.option :value="$value" wire:key="create-status-{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Voter status') }}</flux:label>
                <flux:switch wire:model="isRegisteredVoter" :label="__('Registered voter')" />
                <flux:error name="isRegisteredVoter" />
            </flux:field>

            <flux:field class="lg:col-span-4">
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Birthplace') }}</flux:label>
                <flux:input wire:model="birthplace" />
                <flux:error name="birthplace" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Occupation') }}</flux:label>
                <flux:input wire:model="occupation" />
                <flux:error name="occupation" />
            </flux:field>

            <flux:field class="lg:col-span-2">
                <flux:label>{{ __('Remarks') }}</flux:label>
                <flux:input wire:model="remarks" />
                <flux:error name="remarks" />
            </flux:field>

            <div class="lg:col-span-4">
                <flux:button type="submit" variant="primary" icon="user-plus" wire:loading.attr="disabled">{{ __('Create resident') }}</flux:button>
            </div>
        </form>
        </x-panel>
    @endif

    <x-panel :title="__('Resident records')" :padding="false">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row dark:border-slate-700">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search resident name, code, or address…')" />
            </div>
            @if ($this->canViewBarangayWide())
                <flux:select wire:model.live="purokFilter" class="lg:w-48">
                    <flux:select.option value="">{{ __('All Puroks') }}</flux:select.option>
                    @foreach ($this->puroks as $purok)
                        <flux:select.option :value="$purok->id" wire:key="filter-resident-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:select wire:model.live="statusFilter" class="lg:w-48">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->residentStatuses as $value => $label)
                    <flux:select.option :value="$value" wire:key="filter-status-{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="voterFilter" class="lg:w-48">
                <flux:select.option value="">{{ __('All voters') }}</flux:select.option>
                <flux:select.option value="registered">{{ __('Registered voters') }}</flux:select.option>
                <flux:select.option value="not_registered">{{ __('Not registered') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table min-w-[900px]">
                <thead>
                    <tr>
                        <th>{{ __('Resident') }}</th>
                        <th>{{ __('Sex') }}</th>
                        <th>{{ __('Birthdate') }}</th>
                        <th>{{ __('Purok') }}</th>
                        <th>{{ __('Household') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->residents as $resident)
                        <tr wire:key="resident-row-{{ $resident->id }}">
                            <td>
                                <div class="font-medium">{{ $resident->fullName() }}</div>
                                <div class="text-xs text-slate-500">{{ $resident->resident_code }}</div>
                            </td>
                            <td>{{ __(ucfirst($resident->sex)) }}</td>
                            <td>{{ $resident->birthdate->toDateString() }}</td>
                            <td>{{ $resident->purok->name }}</td>
                            <td>{{ $resident->household->household_code }}</td>
                            <td><flux:badge>{{ $resident->status->label() }}</flux:badge></td>
                            <td class="text-right">
                                @if ($this->canManageResidents())
                                    <flux:button size="sm" variant="outline" wire:click="beginEdit({{ $resident->id }})">{{ __('Edit') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :title="__('No residents recorded')" :description="__('Authorized resident records will appear here after creation.')" icon="users" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    @if ($this->canManageResidents() && $editingResidentId !== null)
        <x-panel :title="__('Edit resident')" :description="__('Status changes are audited instead of deleting resident records.')">
            <form wire:submit="updateResident" class="grid gap-4 lg:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('First name') }}</flux:label>
                    <flux:input wire:model="editFirstName" />
                    <flux:error name="editFirstName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Middle name') }}</flux:label>
                    <flux:input wire:model="editMiddleName" />
                    <flux:error name="editMiddleName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Last name') }}</flux:label>
                    <flux:input wire:model="editLastName" />
                    <flux:error name="editLastName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Suffix') }}</flux:label>
                    <flux:input wire:model="editSuffix" />
                    <flux:error name="editSuffix" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Sex') }}</flux:label>
                    <flux:select wire:model="editSex">
                        <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                        <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="editSex" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Birthdate') }}</flux:label>
                    <flux:input type="date" wire:model="editBirthdate" />
                    <flux:error name="editBirthdate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Civil status') }}</flux:label>
                    <flux:select wire:model="editCivilStatus">
                        <flux:select.option value="single">{{ __('Single') }}</flux:select.option>
                        <flux:select.option value="married">{{ __('Married') }}</flux:select.option>
                        <flux:select.option value="widowed">{{ __('Widowed') }}</flux:select.option>
                        <flux:select.option value="separated">{{ __('Separated') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="editCivilStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Contact number') }}</flux:label>
                    <flux:input wire:model="editContactNumber" />
                    <flux:error name="editContactNumber" />
                </flux:field>

                @if ($this->canViewBarangayWide())
                    <flux:field>
                        <flux:label>{{ __('Purok') }}</flux:label>
                        <flux:select wire:model.live="editPurokId">
                            @foreach ($this->puroks as $purok)
                                <flux:select.option :value="$purok->id" wire:key="edit-resident-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editPurokId" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Household') }}</flux:label>
                    <flux:select wire:model="editHouseholdId">
                        @foreach ($this->households as $household)
                            @if (! $this->canViewBarangayWide() || $editPurokId === '' || $household->purok_id === (int) $editPurokId)
                                <flux:select.option :value="$household->id" wire:key="edit-resident-household-{{ $household->id }}">{{ $household->household_code }} — {{ $household->household_head_name }}</flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                    <flux:error name="editHouseholdId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Resident status') }}</flux:label>
                    <flux:select wire:model="editStatus">
                        @foreach ($this->residentStatuses as $value => $label)
                            <flux:select.option :value="$value" wire:key="edit-status-{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Voter status') }}</flux:label>
                    <flux:switch wire:model="editIsRegisteredVoter" :label="__('Registered voter')" />
                    <flux:error name="editIsRegisteredVoter" />
                </flux:field>

                <flux:field class="lg:col-span-4">
                    <flux:label>{{ __('Address') }}</flux:label>
                    <flux:input wire:model="editAddress" />
                    <flux:error name="editAddress" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Birthplace') }}</flux:label>
                    <flux:input wire:model="editBirthplace" />
                    <flux:error name="editBirthplace" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Occupation') }}</flux:label>
                    <flux:input wire:model="editOccupation" />
                    <flux:error name="editOccupation" />
                </flux:field>

                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('Remarks') }}</flux:label>
                    <flux:input wire:model="editRemarks" />
                    <flux:error name="editRemarks" />
                </flux:field>

                <div class="flex gap-2 lg:col-span-4">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save changes') }}</flux:button>
                    <flux:button type="button" variant="outline" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </x-panel>
    @endif
</div>
