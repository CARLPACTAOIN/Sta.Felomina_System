<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Your account now requires a code from your authenticator app when signing in.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the six-digit code shown in your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('Scan this QR code with your authenticator app, then continue to verify a code.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<flux:modal
    name="two-factor-setup-modal"
    class="max-w-lg md:min-w-lg"
    @close="closeModal"
>
        <div class="space-y-6">
            <div class="flex flex-col items-center gap-4 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"><flux:icon.shield-check class="size-6" /></span>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ $this->modalConfig['title'] }}</flux:heading>
                    <flux:text>{{ $this->modalConfig['description'] }}</flux:text>
                </div>
            </div>

            @if ($showVerificationStep)
                <div class="space-y-6">
                    <div
                        class="flex flex-col items-center space-y-3 justify-center"
                        x-data
                        x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                    >
                        <flux:otp
                            name="code"
                            wire:model="code"
                            length="6"
                            label="OTP Code"
                            label:sr-only
                            class="mx-auto"
                        />
                    </div>

                    <div class="flex items-center space-x-3">
                        <flux:button
                            variant="outline"
                            class="flex-1"
                            wire:click="resetVerification"
                        >
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button
                            variant="primary"
                            class="flex-1"
                            wire:click="confirmTwoFactor"
                            wire:loading.attr="disabled"
                            wire:target="confirmTwoFactor"
                            x-bind:disabled="$wire.code.length < 6"
                        >
                            <span wire:loading.remove wire:target="confirmTwoFactor">{{ __('Confirm and enable') }}</span>
                            <span wire:loading wire:target="confirmTwoFactor">{{ __('Verifying…') }}</span>
                        </flux:button>
                    </div>
                </div>
            @else
                @error('setupData')
                    <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}"/>
                @enderror

                <div class="flex justify-center">
                    <div class="relative aspect-square w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700">
                        @empty($qrCodeSvg)
                            <div class="absolute inset-0 flex items-center justify-center bg-white dark:bg-slate-800 animate-pulse">
                                <flux:icon.loading/>
                            </div>
                        @else
                            <div x-data class="flex items-center justify-center h-full p-4">
                                <div
                                    class="rounded-xl bg-white p-3"
                                    :style="($flux.appearance === 'dark' || ($flux.appearance === 'system' && $flux.dark)) ? 'filter: invert(1) brightness(1.5)' : ''"
                                >
                                    {!! $qrCodeSvg !!}
                                </div>
                            </div>
                        @endempty
                    </div>
                </div>

                <div>
                    <flux:button
                        :disabled="$errors->has('setupData')"
                        variant="primary"
                        class="w-full"
                        wire:click="showVerificationIfNecessary"
                        wire:loading.attr="disabled"
                        wire:target="showVerificationIfNecessary"
                    >
                        <span wire:loading.remove wire:target="showVerificationIfNecessary">{{ $this->modalConfig['buttonText'] }}</span>
                        <span wire:loading wire:target="showVerificationIfNecessary">{{ __('Preparing…') }}</span>
                    </flux:button>
                </div>

                <div class="space-y-4">
                    <div class="relative flex items-center justify-center w-full">
                        <div class="absolute inset-0 top-1/2 h-px w-full bg-slate-200 dark:bg-slate-700"></div>
                        <span class="relative bg-white px-3 text-xs font-medium text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            {{ __('Or use a setup key') }}
                        </span>
                    </div>

                    <div
                        class="flex items-center space-x-2"
                        x-data="{
                            setupKey: @js($manualSetupKey),
                            copied: false,
                            async copy() {
                                try {
                                    await navigator.clipboard.writeText(this.setupKey);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 1500);
                                } catch (e) {
                                    console.warn('Could not copy to clipboard');
                                }
                            }
                        }"
                    >
                        <div class="flex w-full items-stretch overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            @empty($manualSetupKey)
                                <div class="flex items-center justify-center w-full p-3 bg-slate-100 dark:bg-slate-800">
                                    <flux:icon.loading variant="mini"/>
                                </div>
                            @else
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $manualSetupKey }}"
                                    class="w-full bg-transparent p-3 font-mono text-sm text-slate-900 outline-none dark:text-slate-100"
                                />

                                <button
                                    @click="copy()"
                                    class="cursor-pointer border-l border-slate-200 px-3 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                    aria-label="{{ __('Copy setup key') }}"
                                >
                                    <flux:icon.document-duplicate x-show="!copied" variant="outline"></flux:icon>
                                    <flux:icon.check
                                        x-show="copied"
                                        variant="solid"
                                        class="text-green-500"
                                    ></flux:icon>
                                </button>
                            @endempty
                        </div>
                    </div>
                </div>
            @endif
        </div>
</flux:modal>
