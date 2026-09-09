<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-800/40"
    wire:cloak
    x-data="{
        showRecoveryCodes: false,
        copied: false,
        async copyCodes() {
            const codes = Array.from(this.$refs.codes.querySelectorAll('[data-recovery-code]'))
                .map((element) => element.textContent.trim())
                .join('\n');

            await navigator.clipboard.writeText(codes);
            this.copied = true;
            setTimeout(() => this.copied = false, 1500);
        },
    }"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200"><flux:icon.key class="size-4" /></span>
            <div><h3 class="text-sm font-semibold text-slate-950 dark:text-white">{{ __('Recovery Codes') }}</h3><p class="mt-1 max-w-xl text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('Store these one-time codes in a secure place in case you lose access to your authenticator app.') }}</p></div>
        </div>
        <flux:button x-show="!showRecoveryCodes" icon="eye" variant="outline" @click="showRecoveryCodes = true" x-bind:aria-expanded="showRecoveryCodes" aria-controls="recovery-codes-section">{{ __('View codes') }}</flux:button>
        <flux:button x-show="showRecoveryCodes" x-cloak icon="eye-slash" variant="outline" @click="showRecoveryCodes = false" x-bind:aria-expanded="showRecoveryCodes" aria-controls="recovery-codes-section">{{ __('Hide codes') }}</flux:button>
    </div>

    <div x-show="showRecoveryCodes" x-cloak x-transition id="recovery-codes-section" class="mt-5 grid gap-3" x-bind:aria-hidden="!showRecoveryCodes">
        @error('recoveryCodes')
            <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
        @enderror

        @if (filled($recoveryCodes))
            <div x-ref="codes" class="grid gap-2 rounded-xl border border-slate-200 bg-white p-4 font-mono text-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-950" role="list" aria-label="{{ __('Recovery codes') }}">
                @foreach ($recoveryCodes as $code)
                    <div role="listitem" data-recovery-code class="select-text rounded-lg bg-slate-50 px-3 py-2 text-slate-800 dark:bg-slate-900 dark:text-slate-100" wire:key="recovery-code-{{ hash('sha256', $code) }}" wire:loading.class="animate-pulse opacity-50">{{ $code }}</div>
                @endforeach
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <flux:button variant="outline" icon="document-duplicate" @click="copyCodes"><span x-show="!copied">{{ __('Copy codes') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span></flux:button>
                <flux:button variant="outline" icon="arrow-path" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes"><span wire:loading.remove wire:target="regenerateRecoveryCodes">{{ __('Regenerate codes') }}</span><span wire:loading wire:target="regenerateRecoveryCodes">{{ __('Regenerating…') }}</span></flux:button>
            </div>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('Each code works once. Regenerating replaces every current code.') }}</p>
        @endif
    </div>
</div>
