<x-layouts::app :title="__('Categorization Rules')">
    <div class="app-content">
        <x-page-header :title="__('Categorization Rules')" :description="__('Manage explainable metadata rules that suggest one Primary Category while preserving manual override.')">
            <x-slot:actions><flux:button variant="primary" icon="plus" disabled>{{ __('New rule') }}</flux:button></x-slot:actions>
        </x-page-header>
        <x-foundation-notice>{{ __('Rules are not simulated here. Creation, matching priority, matched-term explanations, activation, and overrides will be enabled with persisted categories and audit events.') }}</x-foundation-notice>
        <div class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
            <x-panel :title="__('Rules')" :description="__('Active rules are evaluated by priority.')" :padding="false">
                <x-empty-state :title="__('No categorization rules yet')" :description="__('Create rules using title, subject, keywords, and optional document type conditions.')" icon="adjustments-horizontal" />
            </x-panel>
            <x-panel :title="__('How suggestions work')">
                <div class="space-y-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    <p>{{ __('Rules evaluate structured metadata only. They do not use OCR or AI classification.') }}</p>
                    <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30">
                        <p class="font-semibold text-emerald-900 dark:text-emerald-100">{{ __('Explainable by design') }}</p>
                        <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">{{ __('Users see matched terms and may select a different final category. The override remains auditable.') }}</p>
                    </div>
                </div>
            </x-panel>
        </div>
    </div>
</x-layouts::app>
