<?php

use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Requestable Document Types')] class extends Component
{
    public ?int $editingTypeId = null;
    public bool $isRequestable = false;
    public bool $requestsEnabled = false;
    public string $standardFee = '';

    public function mount(): void { $this->manager(); }
    public function beginEdit(int $typeId): void { $type = $this->documentTypes()->findOrFail($typeId); $this->editingTypeId = $type->id; $this->isRequestable = $type->is_requestable; $this->requestsEnabled = $type->requests_enabled; $this->standardFee = $type->standard_fee ?? ''; }
    public function cancelEdit(): void { $this->reset(['editingTypeId', 'isRequestable', 'requestsEnabled', 'standardFee']); }
    public function save(): void
    {
        $actor = $this->manager(); abort_unless($this->editingTypeId !== null, 404);
        $validated = $this->validate(['isRequestable' => ['boolean'], 'requestsEnabled' => ['boolean'], 'standardFee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99']]);
        if ($validated['requestsEnabled'] && (! $validated['isRequestable'] || blank($validated['standardFee']))) { $this->addError('requestsEnabled', __('A requestable type needs a confirmed non-negative standard fee before requests can be enabled.')); return; }
        $type = DocumentType::query()->findOrFail($this->editingTypeId);
        $type->forceFill(['is_requestable' => $validated['isRequestable'], 'requests_enabled' => $validated['requestsEnabled'], 'standard_fee' => blank($validated['standardFee']) ? null : $validated['standardFee']])->save();
        AuditLog::record($actor, 'requestable_document_type_configured', $type, 'Requestable document type configuration updated.', ['is_requestable' => $type->is_requestable, 'requests_enabled' => $type->requests_enabled, 'standard_fee' => $type->standard_fee]);
        $this->cancelEdit(); Flux::toast(variant: 'success', text: __('Request configuration saved.'));
    }
    #[Computed] public function documentTypes(): EloquentCollection { return DocumentType::query()->orderBy('name')->get(); }
    private function manager(): User { $user = Auth::user(); abort_unless($user instanceof User && $user->can('resident-document-requests.configure-types'), 403); return $user; }
}; ?>

<section class="app-content max-w-6xl"><x-page-header :eyebrow="__('Captain configuration')" :title="__('Requestable Document Types')" :description="__('Enable in-person requests only after confirming the standard cash fee.')" />
    <x-panel :title="__('Document type request settings')"><div class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($this->documentTypes as $type)<article class="grid gap-4 py-4 lg:grid-cols-[1fr_280px]" wire:key="requestable-type-{{ $type->id }}"><div><p class="font-semibold">{{ $type->name }}</p><p class="text-sm text-slate-500">{{ $type->code }} · {{ $type->number_prefix }} · {{ $type->is_active ? __('Official record active') : __('Official record inactive') }}</p></div>@if($editingTypeId === $type->id)<div class="grid gap-3"><flux:checkbox wire:model="isRequestable" :label="__('Requestable type')" /><flux:checkbox wire:model="requestsEnabled" :label="__('Accept new requests')" /><flux:field><flux:label>{{ __('Standard fee') }}</flux:label><flux:input wire:model="standardFee" type="number" min="0" step="0.01" /><flux:error name="standardFee" /></flux:field><flux:error name="requestsEnabled" /><div class="flex justify-end gap-2"><flux:button size="sm" variant="outline" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button><flux:button size="sm" variant="primary" wire:click="save">{{ __('Save') }}</flux:button></div></div>@else<div class="flex items-center justify-between gap-3"><div class="text-sm"><p>{{ $type->requests_enabled ? __('Enabled') : __('Disabled') }}</p><p class="text-slate-500">{{ $type->standard_fee === null ? __('Fee not confirmed') : '₱'.number_format((float) $type->standard_fee, 2) }}</p></div><flux:button size="sm" variant="outline" wire:click="beginEdit({{ $type->id }})">{{ __('Configure') }}</flux:button></div>@endif</article>@endforeach</div></x-panel>
</section>
