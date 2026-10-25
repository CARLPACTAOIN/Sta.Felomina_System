<?php

use App\Enums\StaffPermission;
use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\PurokResolutionFile;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Puroks')] class extends Component
{
    use WithFileUploads;

    public string $search = '';
    public string $name = '';
    public string $code = '';
    public string $sortOrder = '1';
    public bool $isActive = true;
    public ?TemporaryUploadedFile $resolutionPdf = null;

    public ?int $editingPurokId = null;
    public string $editName = '';
    public string $editCode = '';
    public string $editSortOrder = '';
    public bool $editIsActive = true;
    public ?TemporaryUploadedFile $replacementResolutionPdf = null;

    public function mount(): void
    {
        $this->sortOrder = (string) ((Purok::query()->max('sort_order') ?? 0) + 1);
    }

    public function createPurok(): void
    {
        $actor = $this->manager();
        $validated = $this->validate($this->createRules(), attributes: $this->validationAttributes());

        /** @var TemporaryUploadedFile $resolutionPdf */
        $resolutionPdf = $validated['resolutionPdf'];
        $storedPath = $resolutionPdf->store('purok-resolutions');

        try {
            DB::transaction(function () use ($actor, $validated, $resolutionPdf, $storedPath): void {
                $purok = Purok::create([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'sort_order' => (int) $validated['sortOrder'],
                    'is_active' => $validated['isActive'],
                ]);

                $resolutionFile = $purok->resolutionFiles()->create([
                    'path' => $storedPath,
                    'original_name' => $resolutionPdf->getClientOriginalName(),
                    'mime_type' => $resolutionPdf->getMimeType() ?? 'application/pdf',
                    'size' => $resolutionPdf->getSize(),
                    'uploaded_by_id' => $actor->id,
                    'uploaded_at' => now(),
                ]);

                $purok->currentResolutionFile()->associate($resolutionFile);
                $purok->save();

                AuditLog::record($actor, 'purok_created', $purok, 'Purok created with resolution attachment.', [
                    'code' => $purok->code,
                    'resolution_file_id' => $resolutionFile->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::delete($storedPath);

            throw $exception;
        }

        $this->resetCreateForm();

        Flux::toast(variant: 'success', text: __('Purok created.'));
    }

    public function beginEdit(int $purokId): void
    {
        $this->manager();

        $purok = Purok::query()->find($purokId);

        abort_unless($purok instanceof Purok, 404);

        $this->editingPurokId = $purok->id;
        $this->editName = $purok->name;
        $this->editCode = $purok->code;
        $this->editSortOrder = (string) $purok->sort_order;
        $this->editIsActive = $purok->is_active;
        $this->replacementResolutionPdf = null;
    }

    public function updatePurok(): void
    {
        $actor = $this->manager();

        abort_unless($this->editingPurokId !== null, 404);

        $purok = Purok::query()->find($this->editingPurokId);

        abort_unless($purok instanceof Purok, 404);

        $validated = $this->validate($this->updateRules($purok), attributes: $this->validationAttributes('edit'));
        $wasActive = $purok->is_active;

        $purok->forceFill([
            'name' => $validated['editName'],
            'code' => $validated['editCode'],
            'sort_order' => (int) $validated['editSortOrder'],
            'is_active' => $validated['editIsActive'],
        ])->save();

        AuditLog::record($actor, 'purok_updated', $purok, 'Purok details updated.', [
            'code' => $purok->code,
        ]);

        if ($wasActive !== $purok->is_active) {
            AuditLog::record(
                $actor,
                $purok->is_active ? 'purok_activated' : 'purok_deactivated',
                $purok,
                $purok->is_active ? 'Purok activated.' : 'Purok deactivated.',
            );
        }

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: __('Purok updated.'));
    }

    public function replaceResolution(): void
    {
        $actor = $this->manager();

        abort_unless($this->editingPurokId !== null, 404);

        $purok = Purok::query()->find($this->editingPurokId);

        abort_unless($purok instanceof Purok, 404);

        $validated = $this->validate([
            'replacementResolutionPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], attributes: [
            'replacementResolutionPdf' => __('resolution PDF'),
        ]);

        /** @var TemporaryUploadedFile $resolutionPdf */
        $resolutionPdf = $validated['replacementResolutionPdf'];
        $storedPath = $resolutionPdf->store('purok-resolutions');
        $previousResolutionFileId = $purok->current_resolution_file_id;

        try {
            DB::transaction(function () use ($actor, $purok, $resolutionPdf, $storedPath, $previousResolutionFileId): void {
                $resolutionFile = $purok->resolutionFiles()->create([
                    'path' => $storedPath,
                    'original_name' => $resolutionPdf->getClientOriginalName(),
                    'mime_type' => $resolutionPdf->getMimeType() ?? 'application/pdf',
                    'size' => $resolutionPdf->getSize(),
                    'uploaded_by_id' => $actor->id,
                    'uploaded_at' => now(),
                ]);

                $purok->currentResolutionFile()->associate($resolutionFile);
                $purok->save();

                AuditLog::record($actor, 'purok_resolution_replaced', $purok, 'Purok resolution attachment replaced.', [
                    'previous_resolution_file_id' => $previousResolutionFileId,
                    'resolution_file_id' => $resolutionFile->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::delete($storedPath);

            throw $exception;
        }

        $this->replacementResolutionPdf = null;
        unset($this->editingPurok);

        Flux::toast(variant: 'success', text: __('Resolution attachment replaced.'));
    }

    public function deletePurok(int $purokId): void
    {
        $actor = $this->manager();

        $purok = Purok::query()
            ->with('resolutionFiles')
            ->find($purokId);

        abort_unless($purok instanceof Purok, 404);

        if ($purok->users()->exists() || $purok->households()->exists() || $purok->residents()->exists()) {
            throw ValidationException::withMessages([
                'purok' => __('A Purok assigned to staff, households, or residents cannot be deleted. Deactivate it instead.'),
            ]);
        }

        $paths = $purok->resolutionFiles->pluck('path')->all();

        DB::transaction(function () use ($actor, $purok): void {
            AuditLog::record($actor, 'purok_deleted', $purok, 'Purok deleted.', [
                'name' => $purok->name,
                'code' => $purok->code,
            ]);

            $purok->delete();
        });

        Storage::delete($paths);

        if ($this->editingPurokId === $purokId) {
            $this->cancelEdit();
        }

        Flux::toast(variant: 'success', text: __('Purok deleted.'));
    }

    public function cancelEdit(): void
    {
        $this->editingPurokId = null;
        $this->editName = '';
        $this->editCode = '';
        $this->editSortOrder = '';
        $this->editIsActive = true;
        $this->replacementResolutionPdf = null;
    }

    /**
     * @return EloquentCollection<int, Purok>
     */
    #[Computed]
    public function puroks(): EloquentCollection
    {
        return Purok::query()
            ->with('currentResolutionFile')
            ->withCount(['users', 'households', 'residents'])
            ->when($this->search !== '', function (Builder $query): void {
                $search = mb_strtolower($this->search);

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(code) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function editingPurok(): ?Purok
    {
        if ($this->editingPurokId === null) {
            return null;
        }

        return Purok::query()
            ->with(['currentResolutionFile', 'resolutionFiles.uploader'])
            ->find($this->editingPurokId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('puroks', 'name')],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('puroks', 'code')],
            'sortOrder' => ['required', 'integer', 'min:1', 'max:255'],
            'isActive' => ['boolean'],
            'resolutionPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function updateRules(Purok $purok): array
    {
        return [
            'editName' => ['required', 'string', 'max:255', Rule::unique('puroks', 'name')->ignore($purok)],
            'editCode' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('puroks', 'code')->ignore($purok)],
            'editSortOrder' => ['required', 'integer', 'min:1', 'max:255'],
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
                'editCode' => __('code'),
                'editSortOrder' => __('display order'),
                'editIsActive' => __('active status'),
            ];
        }

        return [
            'name' => __('name'),
            'code' => __('code'),
            'sortOrder' => __('display order'),
            'isActive' => __('active status'),
            'resolutionPdf' => __('resolution PDF'),
        ];
    }

    private function manager(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->can(StaffPermission::ManagePuroks->value), 403);

        return $user;
    }

    private function resetCreateForm(): void
    {
        $this->name = '';
        $this->code = '';
        $this->sortOrder = (string) ((Purok::query()->max('sort_order') ?? 0) + 1);
        $this->isActive = true;
        $this->resolutionPdf = null;
    }
}; ?>

<section class="app-content max-w-7xl">
    <x-page-header
        :title="__('Puroks')"
        :description="__('Maintain the official Purok list and its supporting barangay resolutions.')"
    />

    <div class="grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <x-panel :title="__('Add Purok')" :description="__('A signed resolution PDF is required before the Purok can be recorded.')">
            <form wire:submit="createPurok" class="grid gap-4">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="name" placeholder="{{ __('Purok 8') }}" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="code" placeholder="{{ __('purok-8') }}" />
                    <flux:description>{{ __('Use lowercase letters, numbers, and hyphens only.') }}</flux:description>
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Display order') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input type="number" min="1" max="255" wire:model="sortOrder" />
                    <flux:error name="sortOrder" />
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="isActive" :label="__('Active Purok')" />
                    <flux:error name="isActive" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Resolution PDF') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="resolutionPdf" type="file" accept="application/pdf" />
                    <flux:description>{{ __('PDF only, up to 10 MB. It is kept privately and available to authorized administrators only.') }}</flux:description>
                    <flux:error name="resolutionPdf" />
                </flux:field>

                <flux:button variant="primary" type="submit" icon="plus" wire:loading.attr="disabled" wire:target="resolutionPdf,createPurok">
                    <span wire:loading.remove wire:target="createPurok">{{ __('Create Purok') }}</span>
                    <span wire:loading wire:target="createPurok">{{ __('Creating...') }}</span>
                </flux:button>
            </form>
        </x-panel>

        <x-panel :title="__('Purok directory')" :description="__('Usage counts make it clear when a Purok must be deactivated instead of deleted.')" :padding="false">
            @error('purok')
                <div class="border-b border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">{{ $message }}</div>
            @enderror

            <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or code')" />
            </div>

            <div class="overflow-x-auto">
                <table class="data-table min-w-[920px]">
                    <thead>
                        <tr>
                            <th>{{ __('Purok') }}</th>
                            <th>{{ __('Order') }}</th>
                            <th>{{ __('Usage') }}</th>
                            <th>{{ __('Resolution') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->puroks as $purok)
                            <tr wire:key="purok-row-{{ $purok->id }}">
                                <td>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $purok->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $purok->code }}</p>
                                </td>
                                <td>{{ $purok->sort_order }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1 text-xs">
                                        <flux:badge size="sm">{{ trans_choice(':count staff', $purok->users_count, ['count' => $purok->users_count]) }}</flux:badge>
                                        <flux:badge size="sm">{{ trans_choice(':count households', $purok->households_count, ['count' => $purok->households_count]) }}</flux:badge>
                                        <flux:badge size="sm">{{ trans_choice(':count residents', $purok->residents_count, ['count' => $purok->residents_count]) }}</flux:badge>
                                    </div>
                                </td>
                                <td>
                                    @if ($purok->currentResolutionFile !== null)
                                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('puroks.resolution.download', $purok)" target="_blank">{{ __('Open PDF') }}</flux:button>
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('None recorded') }}</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$purok->is_active ? __('Active') : __('Inactive')" /></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="beginEdit({{ $purok->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            icon="trash"
                                            class="text-red-700 dark:text-red-300"
                                            wire:click="deletePurok({{ $purok->id }})"
                                            wire:confirm="{{ __('Delete this Purok? This is allowed only when it has no staff, household, or resident records.') }}"
                                        >{{ __('Delete') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No Puroks match this search.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>

    @if ($this->editingPurok !== null)
        @php($editingPurok = $this->editingPurok)
        <x-panel class="mt-5" :title="__('Edit Purok')" :description="__('Changes and resolution replacements are recorded in the audit log.')">
            <form wire:submit="updatePurok" class="grid gap-4 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="editCode" />
                    <flux:error name="editCode" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Display order') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input type="number" min="1" max="255" wire:model="editSortOrder" />
                    <flux:error name="editSortOrder" />
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="editIsActive" :label="__('Active Purok')" />
                    <flux:description>{{ __('Inactive Puroks remain in historical records but are unavailable for new assignments.') }}</flux:description>
                    <flux:error name="editIsActive" />
                </flux:field>

                <div class="flex justify-end gap-3 lg:col-span-2">
                    <flux:button variant="outline" type="button" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                </div>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-700">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Resolution attachment') }}</h3>
                @if ($editingPurok->currentResolutionFile !== null)
                    <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <p class="font-semibold">{{ $editingPurok->currentResolutionFile->original_name }}</p>
                        <p class="mt-1 text-xs">{{ __('Uploaded :date by :name', ['date' => $editingPurok->currentResolutionFile->uploaded_at->toDayDateTimeString(), 'name' => $editingPurok->currentResolutionFile->uploader?->name ?? __('Unknown staff')]) }}</p>
                        <flux:button class="mt-3" size="sm" variant="outline" icon="arrow-down-tray" :href="route('puroks.resolution.download', $editingPurok)" target="_blank">{{ __('Open current PDF') }}</flux:button>
                    </div>
                @else
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">{{ __('This legacy Purok has no resolution PDF recorded yet.') }}</div>
                @endif

                <form wire:submit="replaceResolution" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <flux:field>
                        <flux:label>{{ __('Replace with a new PDF') }}</flux:label>
                        <flux:input wire:model="replacementResolutionPdf" type="file" accept="application/pdf" />
                        <flux:description>{{ __('PDF only, up to 10 MB. Earlier resolution files remain in the audit history.') }}</flux:description>
                        <flux:error name="replacementResolutionPdf" />
                    </flux:field>
                    <flux:button variant="outline" type="submit" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="replacementResolutionPdf,replaceResolution">
                        <span wire:loading.remove wire:target="replaceResolution">{{ __('Replace PDF') }}</span>
                        <span wire:loading wire:target="replaceResolution">{{ __('Uploading...') }}</span>
                    </flux:button>
                </form>

                @if ($editingPurok->resolutionFiles->count() > 1)
                    <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Earlier resolution files') }}</p>
                        <ul class="mt-2 grid gap-2 text-sm">
                            @foreach ($editingPurok->resolutionFiles->where('id', '!=', $editingPurok->current_resolution_file_id) as $resolutionFile)
                                <li wire:key="resolution-history-{{ $resolutionFile->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
                                    <span class="text-slate-700 dark:text-slate-200">{{ $resolutionFile->original_name }}</span>
                                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('puroks.resolution-files.download', [$editingPurok, $resolutionFile])" target="_blank">{{ __('Open PDF') }}</flux:button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </x-panel>
    @endif
</section>
