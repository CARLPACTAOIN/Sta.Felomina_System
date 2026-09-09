<x-layouts::app :title="__('Households')">
    <div class="app-content">
        <x-page-header :title="__('Households')" :description="__('Group residents under shared household information and reduce repeated address encoding.')">
            <x-slot:actions><flux:button variant="primary" icon="plus" disabled>{{ __('New household') }}</flux:button></x-slot:actions>
        </x-page-header>
        <x-foundation-notice>{{ __('Household actions will be enabled together with resident records and assigned-purok authorization.') }}</x-foundation-notice>
        <x-panel :padding="false">
            <div class="border-b border-slate-200 p-4 dark:border-slate-700"><flux:input icon="magnifying-glass" :placeholder="__('Search household code, head, or address…')" /></div>
            <div class="overflow-x-auto"><table class="data-table min-w-[760px]"><thead><tr><th>{{ __('Household') }}</th><th>{{ __('Household head') }}</th><th>{{ __('Purok') }}</th><th>{{ __('Address') }}</th><th>{{ __('Members') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody></tbody></table></div>
            <x-empty-state :title="__('No households recorded')" :description="__('Household records will hold shared address and head-of-household information for related residents.')" icon="home-modern" />
        </x-panel>
    </div>
</x-layouts::app>
