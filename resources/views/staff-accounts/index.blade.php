<x-layouts::app :title="__('Staff Accounts')">
    <div class="app-content">
        <x-page-header :title="__('Staff Accounts')" :description="__('Manage internal accounts for the Captain, Secretary, and Purok Chairpersons.')">
            <x-slot:actions><flux:button variant="primary" icon="user-plus" disabled>{{ __('Add staff account') }}</flux:button></x-slot:actions>
        </x-page-header>
        <x-foundation-notice>{{ __('Public registration is disabled. Captain-managed creation, role assignment, activation, password reset, and purok assignment will be implemented as a separate authorized workflow.') }}</x-foundation-notice>
        <x-panel :padding="false">
            <div class="overflow-x-auto"><table class="data-table min-w-[760px]"><thead><tr><th>{{ __('Staff member') }}</th><th>{{ __('Role') }}</th><th>{{ __('Purok assignment') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last sign in') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody></tbody></table></div>
            <x-empty-state :title="__('Account management is not connected yet')" :description="__('Existing authenticated accounts remain usable, while administrative account actions await the full Captain-only implementation.')" icon="user-group" />
        </x-panel>
    </div>
</x-layouts::app>
