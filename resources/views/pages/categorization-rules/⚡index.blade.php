<?php

use App\Models\AuditLog;
use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categorization Rules')] class extends Component
{
    public string $name = '';
    public string $documentCategoryId = '';
    public string $documentTypeId = '';
    public string $keywordText = '';
    public string $priority = '100';
    public bool $isActive = true;
    public ?int $editingRuleId = null;
    public string $editName = '';
    public string $editDocumentCategoryId = '';
    public string $editDocumentTypeId = '';
    public string $editKeywordText = '';
    public string $editPriority = '100';
    public bool $editIsActive = true;

    public function createRule(): void
    {
        $actor = $this->manager();
        $validated = $this->validate($this->createRules(), attributes: $this->validationAttributes());
        $keywords = $this->keywordsFrom($validated['keywordText']);

        $rule = CategorizationRule::create([
            'name' => $validated['name'],
            'document_category_id' => (int) $validated['documentCategoryId'],
            'document_type_id' => filled($validated['documentTypeId'] ?? null) ? (int) $validated['documentTypeId'] : null,
            'keywords' => $keywords,
            'priority' => (int) $validated['priority'],
            'is_active' => (bool) ($validated['isActive'] ?? false),
        ]);

        AuditLog::record($actor, 'categorization_rule_created', $rule, 'Categorization rule created.', [
            'category_id' => $rule->document_category_id,
            'document_type_id' => $rule->document_type_id,
            'priority' => $rule->priority,
            'keywords' => $rule->keywords,
            'is_active' => $rule->is_active,
        ]);

        $this->resetCreateForm();

        Flux::toast(variant: 'success', text: __('Categorization rule created.'));
    }

    public function beginEdit(int $ruleId): void
    {
        $this->manager();

        $rule = CategorizationRule::query()->findOrFail($ruleId);

        $this->editingRuleId = $rule->id;
        $this->editName = $rule->name;
        $this->editDocumentCategoryId = (string) $rule->document_category_id;
        $this->editDocumentTypeId = $rule->document_type_id === null ? '' : (string) $rule->document_type_id;
        $this->editKeywordText = implode(', ', $rule->keywords);
        $this->editPriority = (string) $rule->priority;
        $this->editIsActive = $rule->is_active;
    }

    public function cancelEdit(): void
    {
        $this->editingRuleId = null;
        $this->editName = '';
        $this->editDocumentCategoryId = '';
        $this->editDocumentTypeId = '';
        $this->editKeywordText = '';
        $this->editPriority = '100';
        $this->editIsActive = true;
    }

    public function updateRule(): void
    {
        $actor = $this->manager();

        abort_unless($this->editingRuleId !== null, 404);

        $rule = CategorizationRule::query()->findOrFail($this->editingRuleId);
        $validated = $this->validate($this->updateRules($rule), attributes: $this->validationAttributes(prefix: 'edit'));
        $keywords = $this->keywordsFrom($validated['editKeywordText'], 'editKeywordText');
        $originalActive = $rule->is_active;

        $rule->forceFill([
            'name' => $validated['editName'],
            'document_category_id' => (int) $validated['editDocumentCategoryId'],
            'document_type_id' => filled($validated['editDocumentTypeId'] ?? null) ? (int) $validated['editDocumentTypeId'] : null,
            'keywords' => $keywords,
            'priority' => (int) $validated['editPriority'],
            'is_active' => (bool) ($validated['editIsActive'] ?? false),
        ])->save();

        if ($originalActive !== $rule->is_active) {
            AuditLog::record($actor, $rule->is_active ? 'categorization_rule_activated' : 'categorization_rule_deactivated', $rule, $rule->is_active ? 'Categorization rule activated.' : 'Categorization rule deactivated.');
        }

        AuditLog::record($actor, 'categorization_rule_updated', $rule, 'Categorization rule updated.', [
            'category_id' => $rule->document_category_id,
            'document_type_id' => $rule->document_type_id,
            'priority' => $rule->priority,
            'keywords' => $rule->keywords,
            'is_active' => $rule->is_active,
        ]);

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: __('Categorization rule updated.'));
    }

    public function toggleActive(int $ruleId): void
    {
        $actor = $this->manager();
        $rule = CategorizationRule::query()->findOrFail($ruleId);

        $rule->forceFill(['is_active' => ! $rule->is_active])->save();

        AuditLog::record($actor, $rule->is_active ? 'categorization_rule_activated' : 'categorization_rule_deactivated', $rule, $rule->is_active ? 'Categorization rule activated.' : 'Categorization rule deactivated.');

        Flux::toast(variant: 'success', text: $rule->is_active ? __('Rule activated.') : __('Rule deactivated.'));
    }

    /**
     * @return EloquentCollection<int, CategorizationRule>
     */
    #[Computed]
    public function categorizationRules(): EloquentCollection
    {
        return CategorizationRule::query()
            ->with(['category', 'type'])
            ->orderBy('priority')
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
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categorization_rules', 'name')],
            'documentCategoryId' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'documentTypeId' => ['nullable', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'keywordText' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(CategorizationRule $rule): array
    {
        return [
            'editName' => ['required', 'string', 'max:255', Rule::unique('categorization_rules', 'name')->ignore($rule->id)],
            'editDocumentCategoryId' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'editDocumentTypeId' => ['nullable', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'editKeywordText' => ['required', 'string', 'max:2000'],
            'editPriority' => ['required', 'integer', 'min:1', 'max:1000'],
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
                'editName' => __('rule name'),
                'editDocumentCategoryId' => __('target category'),
                'editDocumentTypeId' => __('document type condition'),
                'editKeywordText' => __('keywords'),
                'editPriority' => __('priority'),
                'editIsActive' => __('active status'),
            ];
        }

        return [
            'name' => __('rule name'),
            'documentCategoryId' => __('target category'),
            'documentTypeId' => __('document type condition'),
            'keywordText' => __('keywords'),
            'priority' => __('priority'),
            'isActive' => __('active status'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function keywordsFrom(string $text, string $field = 'keywordText'): array
    {
        $keywords = str($text)
            ->replace(["\r\n", "\n", ';'], ',')
            ->explode(',')
            ->map(fn (string $keyword): string => str($keyword)->squish()->lower()->toString())
            ->filter(fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->values()
            ->all();

        if ($keywords === []) {
            throw ValidationException::withMessages([
                $field => __('Enter at least one usable keyword.'),
            ]);
        }

        return $keywords;
    }

    private function manager(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->role->canManageDocuments(), 403);

        return $user;
    }

    private function resetCreateForm(): void
    {
        $this->name = '';
        $this->documentCategoryId = '';
        $this->documentTypeId = '';
        $this->keywordText = '';
        $this->priority = '100';
        $this->isActive = true;
    }
};
?>

<section class="app-content max-w-7xl">
    <x-page-header
        :title="__('Categorization Rules')"
        :description="__('Manage explainable metadata rules that suggest one Primary Category while preserving manual override.')"
    />

    <div class="grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <x-panel :title="__('New rule')" :description="__('Rules evaluate title, subject, keywords, and optional document type conditions.')">
            <form wire:submit="createRule" class="grid gap-4">
                <flux:field>
                    <flux:label>{{ __('Rule name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Target category') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model="documentCategoryId">
                        <flux:select.option value="">{{ __('Select category') }}</flux:select.option>
                        @foreach ($this->documentCategories as $category)
                            <flux:select.option :value="$category->id" wire:key="create-rule-category-{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="documentCategoryId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document type condition') }}</flux:label>
                    <flux:select wire:model="documentTypeId">
                        <flux:select.option value="">{{ __('Any document type') }}</flux:select.option>
                        @foreach ($this->documentTypes as $type)
                            <flux:select.option :value="$type->id" wire:key="create-rule-type-{{ $type->id }}">{{ $type->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="documentTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Keywords') }} <span class="text-red-600">*</span></flux:label>
                    <flux:textarea wire:model="keywordText" rows="4" :placeholder="__('road, drainage, construction')" />
                    <flux:description>{{ __('Separate words or phrases with commas, semicolons, or new lines.') }}</flux:description>
                    <flux:error name="keywordText" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Priority') }} <span class="text-red-600">*</span></flux:label>
                        <flux:input type="number" min="1" max="1000" wire:model="priority" />
                        <flux:error name="priority" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Status') }}</flux:label>
                        <flux:switch wire:model="isActive" :label="__('Active')" />
                        <flux:error name="isActive" />
                    </flux:field>
                </div>

                <flux:button variant="primary" type="submit" icon="plus" wire:loading.attr="disabled">{{ __('Create rule') }}</flux:button>
            </form>
        </x-panel>

        <x-panel :title="__('Rules')" :description="__('Lower priority numbers run first; ties use the older rule first.')" :padding="false">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[940px]">
                    <thead>
                        <tr>
                            <th>{{ __('Rule') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Type condition') }}</th>
                            <th>{{ __('Keywords') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->categorizationRules as $rule)
                            <tr wire:key="categorization-rule-row-{{ $rule->id }}">
                                <td class="font-medium text-slate-950 dark:text-white">{{ $rule->name }}</td>
                                <td>{{ $rule->category->name }}</td>
                                <td>{{ $rule->type?->name ?? __('Any type') }}</td>
                                <td>
                                    <div class="flex max-w-sm flex-wrap gap-1">
                                        @foreach (array_slice($rule->keywords, 0, 5) as $keyword)
                                            <flux:badge size="sm" wire:key="rule-{{ $rule->id }}-keyword-{{ str($keyword)->slug() }}">{{ $keyword }}</flux:badge>
                                        @endforeach
                                        @if (count($rule->keywords) > 5)
                                            <flux:badge size="sm">{{ __('+:count', ['count' => count($rule->keywords) - 5]) }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $rule->priority }}</td>
                                <td><x-status-badge :status="$rule->is_active ? __('Active') : __('Inactive')" /></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="beginEdit({{ $rule->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button size="sm" variant="outline" icon="{{ $rule->is_active ? 'pause-circle' : 'play-circle' }}" wire:click="toggleActive({{ $rule->id }})">{{ $rule->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state :title="__('No categorization rules yet')" :description="__('Create rules using title, subject, keywords, and optional document type conditions.')" icon="adjustments-horizontal" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>

    @if ($editingRuleId !== null)
        <x-panel class="mt-5" :title="__('Edit rule')" :description="__('Changes affect future suggestions. Existing records keep their saved final category.')">
            <form wire:submit="updateRule" class="grid gap-4 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Rule name') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Target category') }} <span class="text-red-600">*</span></flux:label>
                    <flux:select wire:model="editDocumentCategoryId">
                        @foreach ($this->documentCategories as $category)
                            <flux:select.option :value="$category->id" wire:key="edit-rule-category-{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editDocumentCategoryId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Document type condition') }}</flux:label>
                    <flux:select wire:model="editDocumentTypeId">
                        <flux:select.option value="">{{ __('Any document type') }}</flux:select.option>
                        @foreach ($this->documentTypes as $type)
                            <flux:select.option :value="$type->id" wire:key="edit-rule-type-{{ $type->id }}">{{ $type->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editDocumentTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Priority') }} <span class="text-red-600">*</span></flux:label>
                    <flux:input type="number" min="1" max="1000" wire:model="editPriority" />
                    <flux:error name="editPriority" />
                </flux:field>

                <flux:field class="lg:col-span-2">
                    <flux:label>{{ __('Keywords') }} <span class="text-red-600">*</span></flux:label>
                    <flux:textarea wire:model="editKeywordText" rows="3" />
                    <flux:error name="editKeywordText" />
                </flux:field>

                <div class="lg:col-span-2">
                    <flux:field>
                        <flux:switch wire:model="editIsActive" :label="__('Rule active')" />
                        <flux:error name="editIsActive" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3 lg:col-span-2">
                    <flux:button variant="outline" type="button" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">{{ __('Save changes') }}</flux:button>
                </div>
            </form>
        </x-panel>
    @endif
</section>
