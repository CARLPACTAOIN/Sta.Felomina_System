<?php

use App\Models\DocumentType;
use App\Models\Resident;
use App\Models\ResidentDocumentRequest;
use App\Models\User;
use App\Services\ResidentDocumentRequestService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Resident Document Request')] class extends Component
{
    public ResidentDocumentRequest $residentDocumentRequest;
    public bool $editing = false;
    public string $residentId = '';
    public string $documentTypeId = '';
    public string $purpose = '';
    public string $intakeNotes = '';
    public string $reason = '';
    public string $receiptReference = '';
    public string $amount = '';
    public string $recipientName = '';

    public function mount(ResidentDocumentRequest $residentDocumentRequest): void
    {
        abort_unless($this->user()->can('view', $residentDocumentRequest), 404);
        $this->residentDocumentRequest = $residentDocumentRequest;
        $this->refreshRequest();
    }

    public function beginEdit(): void
    {
        $this->authorize('update', $this->residentDocumentRequest);
        $this->residentId = (string) $this->residentDocumentRequest->resident_id;
        $this->documentTypeId = (string) $this->residentDocumentRequest->document_type_id;
        $this->purpose = $this->residentDocumentRequest->purpose;
        $this->intakeNotes = $this->residentDocumentRequest->intake_notes ?? '';
        $this->editing = true;
    }

    public function saveChanges(ResidentDocumentRequestService $service): void
    {
        $this->authorize('update', $this->residentDocumentRequest);
        $validated = $this->validate(['residentId' => ['required', 'integer'], 'documentTypeId' => ['required', 'integer'], 'purpose' => ['required', 'string', 'max:500'], 'intakeNotes' => ['nullable', 'string', 'max:2000']]);
        $service->update($this->user(), $this->residentDocumentRequest, $this->chairResidents()->findOrFail((int) $validated['residentId']), DocumentType::query()->findOrFail((int) $validated['documentTypeId']), ['purpose' => $validated['purpose'], 'intake_notes' => $validated['intakeNotes']]);
        $this->editing = false; $this->refreshRequest(); Flux::toast(variant: 'success', text: __('Request corrected.'));
    }

    public function submitRequest(ResidentDocumentRequestService $service): void { $this->authorize('submit', $this->residentDocumentRequest); $service->submit($this->user(), $this->residentDocumentRequest); $this->refreshRequest(); Flux::toast(variant: 'success', text: __('Request submitted to the Secretary.')); }
    public function requestInformation(ResidentDocumentRequestService $service): void { $this->authorize('process', $this->residentDocumentRequest); $service->requestInformation($this->user(), $this->residentDocumentRequest, $this->reason); $this->reason = ''; $this->refreshRequest(); }
    public function cancelRequest(ResidentDocumentRequestService $service): void { $this->authorize('cancel', $this->residentDocumentRequest); $service->cancel($this->user(), $this->residentDocumentRequest, $this->reason); $this->reason = ''; $this->refreshRequest(); }
    public function recordPayment(ResidentDocumentRequestService $service): void { $this->authorize('recordPayment', $this->residentDocumentRequest); $validated = $this->validate(['receiptReference' => ['required', 'string', 'max:100', 'unique:resident_document_request_payments,receipt_reference'], 'amount' => ['required', 'numeric', 'min:0']]); $service->recordPayment($this->user(), $this->residentDocumentRequest, $validated['receiptReference'], $validated['amount']); $this->receiptReference = ''; $this->amount = ''; $this->refreshRequest(); }
    public function releaseRequest(ResidentDocumentRequestService $service): void { $this->authorize('release', $this->residentDocumentRequest); $validated = $this->validate(['recipientName' => ['required', 'string', 'max:255']]); $service->release($this->user(), $this->residentDocumentRequest, $validated['recipientName']); $this->recipientName = ''; $this->refreshRequest(); }
    public function createLinkedDocument(): RedirectResponse { $this->authorize('process', $this->residentDocumentRequest); return redirect()->route('resident-document-requests.documents.create', $this->residentDocumentRequest); }

    #[Computed] public function chairResidents(): EloquentCollection { return Resident::query()->where('purok_id', $this->residentDocumentRequest->purok_id)->where('status', 'active')->orderBy('last_name')->get(); }
    #[Computed] public function requestTypes(): EloquentCollection { return DocumentType::query()->where('is_active', true)->where('is_requestable', true)->where('requests_enabled', true)->whereNotNull('standard_fee')->orderBy('name')->get(); }
    private function refreshRequest(): void { $this->residentDocumentRequest->refresh()->load(['documentType', 'purok', 'documentRecord', 'payment.receiver', 'statusHistories.actor']); }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 403); return $user; }
}; ?>

<section class="app-content max-w-6xl">
    <x-page-header :eyebrow="__('Resident service request')" :title="$residentDocumentRequest->reference" :description="$residentDocumentRequest->resident_name_snapshot.' · '.$residentDocumentRequest->documentType->name">
        <x-slot:actions><flux:button variant="outline" :href="route('resident-document-requests.index')" wire:navigate>{{ __('All requests') }}</flux:button></x-slot:actions>
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid gap-5">
            <x-panel :title="__('Request details')"><div class="grid gap-4 sm:grid-cols-2"><div><p class="text-xs font-semibold uppercase text-slate-400">{{ __('Resident') }}</p><p class="mt-1 font-medium">{{ $residentDocumentRequest->resident_name_snapshot }}</p><p class="text-sm text-slate-500">{{ $residentDocumentRequest->address_snapshot }} · {{ $residentDocumentRequest->purok_name_snapshot }}</p></div><div><p class="text-xs font-semibold uppercase text-slate-400">{{ __('Expected fee') }}</p><p class="mt-1 font-medium">₱{{ number_format((float) $residentDocumentRequest->expected_fee, 2) }}</p><p class="text-sm text-slate-500">{{ $residentDocumentRequest->documentType->name }}</p></div><div class="sm:col-span-2"><p class="text-xs font-semibold uppercase text-slate-400">{{ __('Purpose') }}</p><p class="mt-1">{{ $residentDocumentRequest->purpose }}</p>@if($residentDocumentRequest->intake_notes)<p class="mt-2 text-sm text-slate-500">{{ $residentDocumentRequest->intake_notes }}</p>@endif</div></div></x-panel>
            @if ($editing)<form wire:submit="saveChanges"><x-panel :title="__('Correct request')"><div class="grid gap-4"><flux:select wire:model="residentId"><flux:select.option value="">{{ __('Select resident') }}</flux:select.option>@foreach($this->chairResidents as $resident)<flux:select.option :value="$resident->id" wire:key="edit-resident-{{ $resident->id }}">{{ $resident->fullName() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="documentTypeId"><flux:select.option value="">{{ __('Select type') }}</flux:select.option>@foreach($this->requestTypes as $type)<flux:select.option :value="$type->id" wire:key="edit-type-{{ $type->id }}">{{ $type->name }}</flux:select.option>@endforeach</flux:select><flux:textarea wire:model="purpose" rows="3" /><flux:textarea wire:model="intakeNotes" rows="3" /><div class="flex justify-end gap-2"><flux:button type="button" variant="outline" wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save correction') }}</flux:button></div></div></x-panel></form>@endif
            @if($residentDocumentRequest->documentRecord)<x-panel :title="__('Official document')"><p class="text-sm text-slate-600 dark:text-slate-300">{{ __('The linked document follows the standard Draft-to-Published workflow.') }}</p><flux:button class="mt-3" variant="outline" :href="route('documents.show', $residentDocumentRequest->documentRecord)" wire:navigate>{{ $residentDocumentRequest->documentRecord->document_number }}</flux:button></x-panel>@endif
            <x-panel :title="__('Status history')"><ol class="grid gap-3">@foreach($residentDocumentRequest->statusHistories as $history)<li class="border-l-2 border-emerald-200 pl-3 dark:border-emerald-900"><p class="font-medium">{{ $history->to_status->label() }}</p><p class="text-xs text-slate-500">{{ $history->actor?->name ?? __('System') }} · {{ $history->created_at->toDayDateTimeString() }}</p>@if($history->reason)<p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $history->reason }}</p>@endif</li>@endforeach</ol></x-panel>
        </div>
        <aside class="grid content-start gap-5"><x-panel :title="__('Current status')"><flux:badge size="lg">{{ $residentDocumentRequest->status->label() }}</flux:badge>@if($residentDocumentRequest->latest_reason)<p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $residentDocumentRequest->latest_reason }}</p>@endif</x-panel>
            @can('update', $residentDocumentRequest)<x-panel :title="__('Chairperson actions')"><div class="grid gap-2"><flux:button variant="outline" wire:click="beginEdit">{{ __('Correct request') }}</flux:button></div></x-panel>@endcan
            @can('submit', $residentDocumentRequest)<x-panel :title="__('Submit request')"><flux:button variant="primary" wire:click="submitRequest">{{ __('Submit to Secretary') }}</flux:button></x-panel>@endcan
            @can('process', $residentDocumentRequest)<x-panel :title="__('Secretary processing')"><div class="grid gap-3">@if(!$residentDocumentRequest->documentRecord)<flux:button variant="primary" wire:click="createLinkedDocument">{{ __('Create linked document') }}</flux:button>@endif<flux:textarea wire:model="reason" rows="3" :placeholder="__('Reason for information request or cancellation')" /><flux:error name="reason" /><div class="flex gap-2"><flux:button size="sm" variant="outline" wire:click="requestInformation">{{ __('Request information') }}</flux:button><flux:button size="sm" variant="danger" wire:click="cancelRequest">{{ __('Cancel') }}</flux:button></div></div></x-panel>@endcan
            @can('cancel', $residentDocumentRequest) @if(auth()->user()?->can('resident-document-requests.manage.assigned'))<x-panel :title="__('Cancel request')"><flux:textarea wire:model="reason" rows="3" :placeholder="__('Required cancellation reason')" /><flux:error name="reason" /><flux:button class="mt-3" variant="danger" wire:click="cancelRequest">{{ __('Cancel request') }}</flux:button></x-panel>@endif @endcan
            @can('recordPayment', $residentDocumentRequest)<x-panel :title="__('Record cash payment')"><div class="grid gap-3"><flux:input wire:model="receiptReference" :placeholder="__('Official receipt reference')" /><flux:error name="receiptReference" /><flux:input wire:model="amount" type="number" step="0.01" :placeholder="__('Amount')" /><flux:error name="amount" /><flux:button variant="primary" wire:click="recordPayment">{{ __('Record payment') }}</flux:button></div></x-panel>@endcan
            @if($residentDocumentRequest->payment)<x-panel :title="__('Payment recorded')"><p>{{ $residentDocumentRequest->payment->receipt_reference }}</p><p class="text-sm text-slate-500">₱{{ number_format((float) $residentDocumentRequest->payment->amount, 2) }} · {{ $residentDocumentRequest->payment->receiver->name }}</p></x-panel>@endif
            @can('release', $residentDocumentRequest)<x-panel :title="__('Release document')"><flux:input wire:model="recipientName" :placeholder="__('Receiving person full name')" /><flux:error name="recipientName" /><flux:button class="mt-3" variant="primary" wire:click="releaseRequest">{{ __('Record release') }}</flux:button></x-panel>@endcan
        </aside>
    </div>
</section>
