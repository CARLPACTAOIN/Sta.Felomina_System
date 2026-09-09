<x-layouts::app :title="__('Create Document Record')">
    <div class="app-content max-w-6xl">
        <x-page-header
            :eyebrow="__('Step 1 of the document workflow')"
            :title="__('Create Document Record')"
            :description="__('Create the Draft metadata first. The permanent QR identity follows; the final PDF is uploaded later.')"
        />

        <x-foundation-notice :title="__('Form interface ready')">{{ __('Saving is intentionally unavailable until the document model, numbering rules, authorization policies, and permanent token service are implemented and tested.') }}</x-foundation-notice>

        <form class="grid gap-5" aria-describedby="document-form-notice">
            <x-panel :title="__('Document metadata')" :description="__('Required information used to identify, retrieve, and categorize the record.')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:field><flux:label>{{ __('Document type') }} <span class="text-red-600">*</span></flux:label><flux:select><flux:select.option>{{ __('Select document type') }}</flux:select.option><flux:select.option>{{ __('Resolution') }}</flux:select.option><flux:select.option>{{ __('Ordinance') }}</flux:select.option><flux:select.option>{{ __('Certificate') }}</flux:select.option><flux:select.option>{{ __('Memorandum') }}</flux:select.option><flux:select.option>{{ __('Correspondence Record') }}</flux:select.option><flux:select.option>{{ __('Meeting Minutes') }}</flux:select.option></flux:select></flux:field>
                    <flux:field><flux:label>{{ __('Document number') }} <span class="text-red-600">*</span></flux:label><flux:input placeholder="RES-2026-001" /><flux:description>{{ __('A suggested number will be available after selecting a type.') }}</flux:description></flux:field>
                    <flux:field class="sm:col-span-2"><flux:label>{{ __('Title') }} <span class="text-red-600">*</span></flux:label><flux:input :placeholder="__('Enter the official document title')" /></flux:field>
                    <flux:field><flux:label>{{ __('Document date') }} <span class="text-red-600">*</span></flux:label><flux:input type="date" /></flux:field>
                    <flux:field><flux:label>{{ __('Primary category') }}</flux:label><flux:select><flux:select.option>{{ __('Run categorization after metadata is saved') }}</flux:select.option></flux:select></flux:field>
                    <flux:field class="sm:col-span-2"><flux:label>{{ __('Subject or description') }} <span class="text-red-600">*</span></flux:label><flux:textarea rows="4" :placeholder="__('Summarize the purpose and subject of this document')" /></flux:field>
                    <flux:field class="sm:col-span-2"><flux:label>{{ __('Keywords') }}</flux:label><flux:input :placeholder="__('Enter structured keywords separated by commas')" /><flux:description>{{ __('Used by explainable categorization rules; PDF text is not scanned.') }}</flux:description></flux:field>
                </div>
            </x-panel>

            <x-panel :title="__('What happens next')">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ([['qr-code', 'Permanent QR identity', 'Created for this record and retained through every lifecycle state.'], ['document-arrow-up', 'Final PDF later', 'Prepare the document externally, insert its QR, then upload the PDF.'], ['shield-check', 'Captain approval', 'Only publication changes the public page to Verified.']] as [$icon, $title, $text])
                        <div class="flex gap-3" wire:key="next-step-{{ str($title)->slug() }}"><span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"><flux:icon :name="$icon" class="size-5" /></span><div><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p><p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $text }}</p></div></div>
                    @endforeach
                </div>
            </x-panel>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="outline" :href="route('documents.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" icon="document-plus" disabled>{{ __('Create Draft Record') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
