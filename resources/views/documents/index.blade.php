<x-layouts::app :title="__('Document Archive')">
    <div class="app-content">
        <x-page-header
            :title="__('Document Archive')"
            :description="__('Search and retrieve official records across their full lifecycle.')"
        >
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Create document record') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        <x-foundation-notice>{{ __('Search, filtering, sorting, pagination, and row actions are prepared here but will be connected when the document domain is implemented.') }}</x-foundation-notice>

        <x-panel :padding="false">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center dark:border-slate-700">
                <div class="min-w-0 flex-1">
                    <flux:input icon="magnifying-glass" :placeholder="__('Search number, title, subject, or keyword…')" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:flex lg:shrink-0">
                    <flux:select :placeholder="__('All types')" class="lg:w-40"><flux:select.option>{{ __('All types') }}</flux:select.option></flux:select>
                    <flux:select :placeholder="__('All statuses')" class="lg:w-44"><flux:select.option>{{ __('All statuses') }}</flux:select.option></flux:select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[820px]">
                    <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Type') }}</th><th>{{ __('Category') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <x-empty-state :title="__('No document records yet')" :description="__('Create a Draft record first. Its permanent verification identity is created before the final PDF is uploaded.')" icon="document-text">
                <x-slot:action><flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Create first Draft') }}</flux:button></x-slot:action>
            </x-empty-state>
        </x-panel>
    </div>
</x-layouts::app>
