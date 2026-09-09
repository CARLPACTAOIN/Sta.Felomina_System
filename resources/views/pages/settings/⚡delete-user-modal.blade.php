<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div class="flex gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300"><flux:icon.exclamation-triangle class="size-5" /></span>
            <div>
                <flux:heading size="lg">{{ __('Delete your account?') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('This permanently removes your staff account and cannot be undone. Enter your password to confirm.') }}</flux:subheading>
            </div>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button" wire:loading.attr="disabled" wire:target="deleteUser">
                <span wire:loading.remove wire:target="deleteUser">{{ __('Permanently delete') }}</span>
                <span wire:loading wire:target="deleteUser">{{ __('Deleting…') }}</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
