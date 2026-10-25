<?php

use App\Models\DocumentType;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use App\Services\ResidentDocumentRequestService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Resident Document Request')] class extends Component
{
    public string $residentId = '';
    public string $documentTypeId = '';
    public string $purpose = '';
    public string $intakeNotes = '';

    public function mount(): void { $this->authorize('create', ResidentDocumentRequest::class); }

    public function save(ResidentDocumentRequestService $service): \Illuminate\Http\RedirectResponse
    {
        $actor = $this->user();
        $validated = $this->validate(['residentId' => ['required', 'integer'], 'documentTypeId' => ['required', 'integer'], 'purpose' => ['required', 'string', 'max:500'], 'intakeNotes' => ['nullable', 'string', 'max:2000']], attributes: ['residentId' => __('resident'), 'documentTypeId' => __('document type')]);
        $resident = Resident::query()->where('purok_id', $actor->purok_id)->findOrFail((int) $validated['residentId']);
        $type = DocumentType::query()->findOrFail((int) $validated['documentTypeId']);
        $request = $service->create($actor, $resident, $type, ['purpose' => $validated['purpose'], 'intake_notes' => $validated['intakeNotes']]);
        session()->flash('status', __('Resident document request saved as Draft.'));
        return redirect()->route('resident-document-requests.show', $request);
    }

    #[Computed] public function residents(): EloquentCollection { return Resident::query()->where('purok_id', $this->user()->purok_id)->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(); }
    #[Computed] public function documentTypes(): EloquentCollection { return DocumentType::query()->where('is_active', true)->where('is_requestable', true)->where('requests_enabled', true)->whereNotNull('standard_fee')->orderBy('name')->get(); }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 403); return $user; }
}; ?>

<section class="app-content max-w-3xl">
    <x-page-header :eyebrow="__('Purok intake')" :title="__('New Document Request')" :description="__('Record the resident’s in-person request. Supporting evidence remains with the office.')" />
    <form wire:submit="save" class="grid gap-5"><x-panel :title="__('Request details')"><div class="grid gap-5">
        <flux:field><flux:label>{{ __('Resident') }} <span class="text-red-600">*</span></flux:label><flux:select wire:model="residentId"><flux:select.option value="">{{ __('Select an Active resident') }}</flux:select.option>@foreach($this->residents as $resident)<flux:select.option :value="$resident->id" wire:key="resident-{{ $resident->id }}">{{ $resident->fullName() }} — {{ $resident->household->household_code }}</flux:select.option>@endforeach</flux:select><flux:error name="residentId" /></flux:field>
        <flux:field><flux:label>{{ __('Document type') }} <span class="text-red-600">*</span></flux:label><flux:select wire:model="documentTypeId"><flux:select.option value="">{{ __('Select a requestable type') }}</flux:select.option>@foreach($this->documentTypes as $type)<flux:select.option :value="$type->id" wire:key="request-type-{{ $type->id }}">{{ $type->name }} — ₱{{ number_format((float) $type->standard_fee, 2) }}</flux:select.option>@endforeach</flux:select><flux:error name="documentTypeId" /></flux:field>
        <flux:field><flux:label>{{ __('Purpose') }} <span class="text-red-600">*</span></flux:label><flux:textarea wire:model="purpose" rows="3" /><flux:error name="purpose" /></flux:field>
        <flux:field><flux:label>{{ __('Intake notes') }}</flux:label><flux:textarea wire:model="intakeNotes" rows="3" /><flux:error name="intakeNotes" /></flux:field>
    </div></x-panel><div class="flex justify-end gap-2"><flux:button variant="outline" :href="route('resident-document-requests.index')" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save Draft') }}</flux:button></div></form>
</section>
