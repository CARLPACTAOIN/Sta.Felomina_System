<?php

use App\Enums\StaffPermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staff Accounts')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $role = '';
    public string $purokId = '';
    public ?string $temporaryPassword = null;
    public ?int $temporaryPasswordUserId = null;
    public ?int $editingUserId = null;
    public string $editName = '';
    public string $editEmail = '';
    public string $editRole = '';
    public string $editPurokId = '';
    public bool $editIsActive = true;

    public function mount(): void
    {
        $this->role = UserRole::Secretary->value;
    }

    public function createStaff(): void
    {
        $actor = $this->captain();

        $validated = $this->validate($this->createRules(), attributes: $this->validationAttributes());
        $selectedRole = UserRole::from($validated['role']);
        $temporaryPassword = $this->newTemporaryPassword();

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $temporaryPassword,
            'purok_id' => $this->purokIdFor($selectedRole, $validated['purokId'] ?? null),
            'is_active' => true,
        ]);

        $staff->syncRoles($selectedRole->value);
        $staff->forceFill(['email_verified_at' => now()])->save();

        AuditLog::record($actor, 'account_created', $staff, 'Staff account created.', [
            'role' => $selectedRole->value,
            'purok_id' => $staff->purok_id,
        ]);

        $this->temporaryPassword = $temporaryPassword;
        $this->temporaryPasswordUserId = $staff->id;
        $this->resetCreateForm();

        Flux::toast(variant: 'success', text: __('Staff account created.'));
    }

    public function beginEdit(int $staffId): void
    {
        $this->captain();

        $staff = User::query()->findOrFail($staffId);

        $this->editingUserId = $staff->id;
        $this->editName = $staff->name;
        $this->editEmail = $staff->email;
        $this->editRole = $staff->staffRole()?->value ?? UserRole::Secretary->value;
        $this->editPurokId = $staff->purok_id === null ? '' : (string) $staff->purok_id;
        $this->editIsActive = $staff->is_active;
    }

    public function cancelEdit(): void
    {
        $this->editingUserId = null;
        $this->editName = '';
        $this->editEmail = '';
        $this->editRole = '';
        $this->editPurokId = '';
        $this->editIsActive = true;
    }

    public function updateStaff(): void
    {
        $actor = $this->captain();

        abort_unless($this->editingUserId !== null, 404);

        $staff = User::query()->findOrFail($this->editingUserId);
        $validated = $this->validate($this->updateRules($staff), attributes: $this->validationAttributes(prefix: 'edit'));
        $nextRole = UserRole::from($validated['editRole']);
        $nextActive = (bool) $validated['editIsActive'];

        if ($staff->is($actor) && ! $nextActive) {
            throw ValidationException::withMessages([
                'editIsActive' => __('You cannot deactivate your own account.'),
            ]);
        }

        if ($this->removesLastActiveCaptain($staff, $nextRole, $nextActive)) {
            throw ValidationException::withMessages([
                'editRole' => __('At least one active Captain account must remain.'),
            ]);
        }

        $originalRole = $staff->staffRole();
        $originalActive = $staff->is_active;

        $staff->forceFill([
            'name' => $validated['editName'],
            'email' => $validated['editEmail'],
            'purok_id' => $this->purokIdFor($nextRole, $validated['editPurokId'] ?? null),
            'is_active' => $nextActive,
        ])->save();
        $staff->syncRoles($nextRole->value);

        if ($originalRole !== $nextRole) {
            AuditLog::record($actor, 'account_role_changed', $staff, 'Staff account role changed.', [
                'from' => $originalRole?->value,
                'to' => $nextRole->value,
            ]);
        }

        if ($originalActive !== $nextActive) {
            AuditLog::record($actor, $nextActive ? 'account_activated' : 'account_deactivated', $staff, $nextActive ? 'Staff account activated.' : 'Staff account deactivated.');
        }

        AuditLog::record($actor, 'account_updated', $staff, 'Staff account updated.', [
            'role' => $nextRole->value,
            'purok_id' => $staff->purok_id,
            'is_active' => $staff->is_active,
        ]);

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: __('Staff account updated.'));
    }

    public function toggleActive(int $staffId): void
    {
        $actor = $this->captain();
        $staff = User::query()->findOrFail($staffId);
        $nextActive = ! $staff->is_active;

        if ($staff->is($actor) && ! $nextActive) {
            throw ValidationException::withMessages([
                'staff' => __('You cannot deactivate your own account.'),
            ]);
        }

        if ($this->removesLastActiveCaptain($staff, $staff->staffRole(), $nextActive)) {
            throw ValidationException::withMessages([
                'staff' => __('At least one active Captain account must remain.'),
            ]);
        }

        $staff->forceFill(['is_active' => $nextActive])->save();

        AuditLog::record($actor, $nextActive ? 'account_activated' : 'account_deactivated', $staff, $nextActive ? 'Staff account activated.' : 'Staff account deactivated.');

        Flux::toast(variant: 'success', text: $nextActive ? __('Staff account activated.') : __('Staff account deactivated.'));
    }

    public function resetPassword(int $staffId): void
    {
        $actor = $this->captain();
        $staff = User::query()->findOrFail($staffId);

        abort_unless(! $staff->is($actor), 403);

        $temporaryPassword = $this->newTemporaryPassword();

        $staff->forceFill(['password' => $temporaryPassword])->save();

        AuditLog::record($actor, 'account_password_reset', $staff, 'Staff account password reset.');

        $this->temporaryPassword = $temporaryPassword;
        $this->temporaryPasswordUserId = $staff->id;

        Flux::toast(variant: 'success', text: __('Temporary password generated.'));
    }

    /**
     * @return EloquentCollection<int, User>
     */
    #[Computed]
    public function staffAccounts(): EloquentCollection
    {
        return User::query()
            ->with(['purok', 'roles'])
            ->orderBy('name')
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
            ->get();
    }

    /**
     * @return array<int, UserRole>
     */
    #[Computed]
    public function roles(): array
    {
        return UserRole::cases();
    }

    private function captain(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->can(StaffPermission::ManageStaffAccounts->value), 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'purokId' => [
                Rule::requiredIf($this->role === UserRole::PurokChairperson->value),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(User $staff): array
    {
        return [
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'editRole' => ['required', Rule::enum(UserRole::class)],
            'editPurokId' => [
                Rule::requiredIf($this->editRole === UserRole::PurokChairperson->value),
                'nullable',
                'integer',
                Rule::exists('puroks', 'id')->where('is_active', true),
            ],
            'editIsActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(string $prefix = ''): array
    {
        if ($prefix === 'edit') {
            return [
                'editName' => __('name'),
                'editEmail' => __('email'),
                'editRole' => __('role'),
                'editPurokId' => __('purok assignment'),
                'editIsActive' => __('active status'),
            ];
        }

        return [
            'name' => __('name'),
            'email' => __('email'),
            'role' => __('role'),
            'purokId' => __('purok assignment'),
        ];
    }

    private function purokIdFor(UserRole $role, mixed $purokId): ?int
    {
        if ($role !== UserRole::PurokChairperson) {
            return null;
        }

        return filled($purokId) ? (int) $purokId : null;
    }

    private function removesLastActiveCaptain(User $staff, ?UserRole $nextRole, bool $nextActive): bool
    {
        if (! $staff->hasStaffRole(UserRole::Captain) || ! $staff->is_active) {
            return false;
        }

        if ($nextRole === UserRole::Captain && $nextActive) {
            return false;
        }

        return User::role(UserRole::Captain->value)
            ->where('is_active', true)
            ->count() <= 1;
    }

    private function newTemporaryPassword(): string
    {
        return Str::random(16);
    }

    private function resetCreateForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->role = UserRole::Secretary->value;
        $this->purokId = '';
    }
}; ?>

<section class="app-content max-w-7xl">
    <x-page-header
        :title="__('Staff Accounts')"
        :description="__('Manage internal Captain, Secretary, and Purok Chairperson accounts. Public registration remains disabled.')"
    />

    @if ($temporaryPassword !== null)
        @php($temporaryUser = $this->staffAccounts->firstWhere('id', $temporaryPasswordUserId))
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
            <p class="font-semibold">{{ __('Temporary password generated') }}</p>
            <p class="mt-1">{{ __('Copy this password now for :name. It is not stored in plaintext and will disappear after another action or page reload.', ['name' => $temporaryUser?->name ?? __('the staff account')]) }}</p>
            <code class="mt-3 block rounded-lg bg-white px-3 py-2 font-mono text-slate-900 dark:bg-slate-900 dark:text-white">{{ $temporaryPassword }}</code>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <x-panel :title="__('Create staff account')" :description="__('Temporary passwords are shown once to the Captain after creation.')">
            <form wire:submit="createStaff" class="grid gap-4">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input type="email" wire:model="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Role') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model.live="role">
                        @foreach ($this->roles as $staffRole)
                            <flux:select.option :value="$staffRole->value" wire:key="create-role-{{ $staffRole->value }}">{{ $staffRole->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Purok assignment') }}</flux:label>
                    <flux:select wire:model="purokId" :disabled="$role !== \App\Enums\UserRole::PurokChairperson->value">
                        <flux:select.option value="">{{ __('No purok assignment') }}</flux:select.option>
                        @foreach ($this->puroks as $purok)
                            <flux:select.option :value="$purok->id" wire:key="create-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Required only for Purok Chairpersons.') }}</flux:description>
                    <flux:error name="purokId" />
                </flux:field>

                <flux:button variant="primary" type="submit" icon="user-plus">{{ __('Create account') }}</flux:button>
            </form>
        </x-panel>

        <x-panel :title="__('Staff directory')" :description="__('Captain-managed internal accounts.')" :padding="false">
            @error('staff')
                <div class="border-b border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">{{ $message }}</div>
            @enderror

            <div class="overflow-x-auto">
                <table class="data-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th>{{ __('Staff member') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Purok assignment') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->staffAccounts as $staff)
                            <tr wire:key="staff-row-{{ $staff->id }}">
                                <td>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $staff->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $staff->email }}</p>
                                </td>
                                <td>{{ $staff->staffRoleLabel() }}</td>
                                <td>{{ $staff->purok?->name ?? __('Not assigned') }}</td>
                                <td><x-status-badge :status="$staff->is_active ? __('Active') : __('Inactive')" /></td>
                                <td>{{ $staff->created_at?->toFormattedDateString() }}</td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="beginEdit({{ $staff->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="outline" icon="key" wire:click="resetPassword({{ $staff->id }})" wire:confirm="{{ __('Generate a new temporary password for this staff account?') }}">{{ __('Reset password') }}</flux:button>
                                        <flux:button size="sm" variant="outline" icon="{{ $staff->is_active ? 'x-circle' : 'check-circle' }}" wire:click="toggleActive({{ $staff->id }})" wire:confirm="{{ $staff->is_active ? __('Deactivate this staff account?') : __('Reactivate this staff account?') }}">{{ $staff->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>

    @if ($editingUserId !== null)
        <x-panel class="mt-5" :title="__('Edit staff account')" :description="__('Role and active-state changes are audited.')">
            <form wire:submit="updateStaff" class="grid gap-4 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input type="email" wire:model="editEmail" />
                    <flux:error name="editEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Role') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model.live="editRole">
                        @foreach ($this->roles as $staffRole)
                            <flux:select.option :value="$staffRole->value" wire:key="edit-role-{{ $staffRole->value }}">{{ $staffRole->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editRole" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Purok assignment') }}</flux:label>
                    <flux:select wire:model="editPurokId" :disabled="$editRole !== \App\Enums\UserRole::PurokChairperson->value">
                        <flux:select.option value="">{{ __('No purok assignment') }}</flux:select.option>
                        @foreach ($this->puroks as $purok)
                            <flux:select.option :value="$purok->id" wire:key="edit-purok-{{ $purok->id }}">{{ $purok->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editPurokId" />
                </flux:field>

                <div class="lg:col-span-2">
                    <flux:field>
                        <flux:switch wire:model="editIsActive" :label="__('Account active')" />
                        <flux:error name="editIsActive" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3 lg:col-span-2">
                    <flux:button variant="outline" type="button" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                </div>
            </form>
        </x-panel>
    @endif
</section>
