<x-layouts::app :title="__('Residents')">
    @php($isPurokChairperson = auth()->user()->role === \App\Enums\UserRole::PurokChairperson)

    <div class="app-content">
        <x-page-header
            :eyebrow="$isPurokChairperson ? __('Assigned purok only') : __('Barangay-wide access')"
            :title="$isPurokChairperson ? __('Purok Residents') : __('Resident Directory')"
            :description="$isPurokChairperson ? __('Search and maintain resident profiles within your assigned purok.') : __('Search resident profiles across all puroks according to your role.')"
        >
            <x-slot:actions><flux:button variant="primary" icon="user-plus" disabled>{{ __('Add resident') }}</flux:button></x-slot:actions>
        </x-page-header>

        <x-foundation-notice>{{ __('Resident creation is deferred until purok assignment, household relationships, duplicate warnings, status rules, and server-side isolation are implemented as one tested slice.') }}</x-foundation-notice>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-metric-card :label="__('Residents')" value="0" icon="users" :hint="$isPurokChairperson ? __('Assigned purok only') : __('All puroks')" />
            <x-metric-card :label="__('Active')" value="0" icon="user-group" :hint="__('Current resident status')" />
            <x-metric-card :label="__('Households')" value="0" icon="home-modern" :hint="__('Shared address records')" />
        </div>

        <x-panel :padding="false">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-700">
                <div class="flex-1"><flux:input icon="magnifying-glass" :placeholder="__('Search resident name, household, or address…')" /></div>
                @unless ($isPurokChairperson)
                    <flux:select class="sm:w-44"><flux:select.option>{{ __('All puroks') }}</flux:select.option></flux:select>
                @endunless
                <flux:select class="sm:w-44"><flux:select.option>{{ __('All statuses') }}</flux:select.option></flux:select>
            </div>
            <div class="overflow-x-auto"><table class="data-table min-w-[800px]"><thead><tr><th>{{ __('Resident') }}</th><th>{{ __('Sex') }}</th><th>{{ __('Birthdate') }}</th><th>{{ __('Purok') }}</th><th>{{ __('Household') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody></tbody></table></div>
            <x-empty-state :title="__('No residents recorded')" :description="__('Search results and authorized resident actions will appear here after the resident domain is connected.')" icon="users" />
        </x-panel>
    </div>
</x-layouts::app>
