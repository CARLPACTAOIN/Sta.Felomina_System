<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $canManageTwoFactor;
    public bool $twoFactorEnabled;
    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="app-content max-w-6xl">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Security')" :subheading="__('Protect your account with a strong password and additional sign-in methods.')">
        <x-panel :title="__('Password')" :description="__('Use a long, unique password that is not shared with another account.')">
            <form method="POST" wire:submit="updatePassword" class="grid gap-5">
                <flux:input wire:model="current_password" :label="__('Current password')" type="password" required autocomplete="current-password" viewable />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="password" :label="__('New password')" type="password" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />
                    <flux:input wire:model="password_confirmation" :label="__('Confirm new password')" type="password" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />
                </div>
                <div class="flex justify-end border-t border-slate-200 pt-5 dark:border-slate-700">
                    <flux:button variant="primary" type="submit" data-test="update-password-button" wire:loading.attr="disabled" wire:target="updatePassword">
                        <span wire:loading.remove wire:target="updatePassword">{{ __('Update password') }}</span>
                        <span wire:loading wire:target="updatePassword">{{ __('Updating…') }}</span>
                    </flux:button>
                </div>
            </form>
        </x-panel>

        @if ($canManageTwoFactor)
            <x-panel :title="__('Two-Factor Authentication')" :description="__('Require a temporary code from your authenticator app when signing in.')">
                <div class="grid gap-5" wire:cloak>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"><flux:icon.shield-check class="size-5" /></span>
                            <div><x-status-badge :status="$twoFactorEnabled ? __('Enabled') : __('Not enabled')" /><p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ $twoFactorEnabled ? __('Your account requires an authenticator code at sign in.') : __('Add a second verification step to your account.') }}</p></div>
                        </div>

                        @if ($twoFactorEnabled)
                            <flux:button variant="danger" wire:click="disable" wire:loading.attr="disabled" wire:target="disable">
                                <span wire:loading.remove wire:target="disable">{{ __('Disable two-factor') }}</span><span wire:loading wire:target="disable">{{ __('Disabling…') }}</span>
                            </flux:button>
                        @else
                            <flux:modal.trigger name="two-factor-setup-modal">
                                <flux:button variant="primary" wire:click="$dispatch('start-two-factor-setup')">{{ __('Enable Two-Factor Authentication') }}</flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>

                    @if ($twoFactorEnabled)
                        <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                    @else
                        <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                    @endif
                </div>
            </x-panel>
        @endif

        @if ($canManagePasskeys)
            <x-panel :title="__('Passkeys')" :description="__('Use a trusted device credential for passwordless sign in when your browser supports it.')">
                <div class="grid gap-5" wire:cloak>
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        @forelse ($passkeys as $passkey)
                            <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-700" wire:key="passkey-{{ $passkey['id'] }}">
                                <div class="flex min-w-0 items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><flux:icon.key class="size-5" /></span><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $passkey['name'] }}</p>@if ($passkey['authenticator'])<flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>@endif</div><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}@if ($passkey['last_used_at_diff']) · {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}@endif</p></div></div>
                                <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $passkey['id'] }})" :aria-label="__('Remove :name', ['name' => $passkey['name']])" />
                            </div>
                        @empty
                            <x-empty-state :title="__('No passkeys yet')" :description="__('Add a passkey to sign in with a supported device without typing your password.')" icon="key" class="py-8" />
                        @endforelse
                    </div>

                    <x-passkey-registration />
                </div>
            </x-panel>
        @endif
    </x-pages::settings.layout>

    <flux:modal name="delete-passkey-modal" class="max-w-md md:min-w-md" @close="closeDeleteModal" wire:model="showDeleteModal">
        <div class="space-y-6">
            <div class="flex gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300"><flux:icon.key class="size-5" /></span><div><flux:heading size="lg">{{ __('Remove passkey?') }}</flux:heading><flux:text class="mt-1">{{ __('You will no longer be able to use “:name” to sign in.', ['name' => $deletingPasskeyName]) }}</flux:text></div></div>
            <div class="flex justify-end gap-2"><flux:button variant="outline" wire:click="closeDeleteModal">{{ __('Cancel') }}</flux:button><flux:button variant="danger" wire:click="deletePasskey" wire:loading.attr="disabled" wire:target="deletePasskey">{{ __('Remove passkey') }}</flux:button></div>
        </div>
    </flux:modal>
</section>
