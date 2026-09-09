<x-layouts::app :title="__('Audit Logs')">
    <div class="app-content">
        <x-page-header :title="__('Audit Logs')" :description="__('Review automatic history for important document, account, and resident changes.')" />
        <x-foundation-notice>{{ __('Audit entries will be application-generated and non-editable. This screen intentionally does not offer manual creation or editing actions.') }}</x-foundation-notice>
        <x-panel :padding="false">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-700"><div class="flex-1"><flux:input icon="magnifying-glass" :placeholder="__('Search actor, action, or record…')" /></div><flux:select class="sm:w-44"><flux:select.option>{{ __('All record types') }}</flux:select.option></flux:select></div>
            <x-empty-state :title="__('No audit events yet')" :description="__('Account, document, and resident events will appear here with actor, action, record, and timestamp.')" icon="clock" />
        </x-panel>
    </div>
</x-layouts::app>
