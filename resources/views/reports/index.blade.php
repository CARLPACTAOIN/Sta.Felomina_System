<x-layouts::app :title="__('Reports')">
    @php($isPurokChairperson = auth()->user()->role === \App\Enums\UserRole::PurokChairperson)
    <div class="app-content">
        <x-page-header
            :title="$isPurokChairperson ? __('Purok Summary') : __('Reports')"
            :description="$isPurokChairperson ? __('Review resident and household summaries limited to your assigned purok.') : __('Build printable document or resident reports from one reusable filter workspace.')"
        >
            <x-slot:actions><flux:button variant="outline" icon="printer" disabled>{{ __('Print') }}</flux:button><flux:button variant="primary" icon="arrow-down-tray" disabled>{{ __('Export PDF') }}</flux:button></x-slot:actions>
        </x-page-header>
        <x-foundation-notice>{{ __('Report filtering and PDF output will be enabled only after their underlying document and resident query layers are complete and authorized.') }}</x-foundation-notice>
        <x-panel :title="__('Report filters')" :description="__('Choose the records included in the output.')">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <flux:select><flux:select.option>{{ $isPurokChairperson ? __('Resident report') : __('Document report') }}</flux:select.option></flux:select>
                <flux:select><flux:select.option>{{ __('Current year') }}</flux:select.option></flux:select>
                <flux:select><flux:select.option>{{ __('All categories') }}</flux:select.option></flux:select>
                <flux:select><flux:select.option>{{ __('All types') }}</flux:select.option></flux:select>
                <flux:select><flux:select.option>{{ __('All statuses') }}</flux:select.option></flux:select>
            </div>
        </x-panel>
        <x-panel :padding="false"><x-empty-state :title="__('No report results')" :description="__('Filtered results and printable output will appear here once data is available.')" icon="chart-bar-square" /></x-panel>
    </div>
</x-layouts::app>
