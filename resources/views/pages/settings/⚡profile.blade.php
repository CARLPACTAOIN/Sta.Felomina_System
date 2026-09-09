<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="app-content max-w-6xl">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Keep the name and email used for your staff account up to date.')">
        <x-panel :title="__('Personal information')" :description="__('These details identify your authenticated staff account. Resident profiles are managed separately.')">
            <form wire:submit="updateProfileInformation" class="grid gap-5">
                <flux:input wire:model="name" :label="__('Full name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" :label="__('Email address')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            <p>{{ __('Your email address has not been verified.') }}</p>
                            <button type="button" class="mt-2 font-semibold underline underline-offset-4" wire:click="resendVerificationNotification" wire:loading.attr="disabled" wire:target="resendVerificationNotification">
                                <span wire:loading.remove wire:target="resendVerificationNotification">{{ __('Send a new verification link') }}</span>
                                <span wire:loading wire:target="resendVerificationNotification">{{ __('Sending…') }}</span>
                            </button>

                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-2 font-medium text-emerald-700 dark:text-emerald-300">{{ __('A new verification link has been sent.') }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-slate-200 pt-5 dark:border-slate-700">
                    <flux:button variant="primary" type="submit" data-test="update-profile-button" wire:loading.attr="disabled" wire:target="updateProfileInformation">
                        <span wire:loading.remove wire:target="updateProfileInformation">{{ __('Save changes') }}</span>
                        <span wire:loading wire:target="updateProfileInformation">{{ __('Saving…') }}</span>
                    </flux:button>
                </div>
            </form>
        </x-panel>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
