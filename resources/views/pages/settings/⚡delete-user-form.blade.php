<?php

use Livewire\Component;

new class extends Component {}; ?>

<section id="delete-account" class="rounded-2xl border border-red-200 bg-white p-5 sm:p-6">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700"><flux:icon.trash class="size-5" /></span>
            <div><h3 class="text-sm font-semibold text-slate-950">{{ __('Delete account') }}</h3><p class="mt-1 max-w-xl text-sm leading-6 text-slate-500">{{ __('Permanently remove your own staff account. This is separate from Captain-managed staff administration.') }}</p></div>
        </div>

        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button variant="danger" data-test="delete-user-button">{{ __('Delete account') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
