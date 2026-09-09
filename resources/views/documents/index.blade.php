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

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                {{ session('status') }}
                @if (session('created_document_number'))
                    <span class="ml-1">{{ session('created_document_number') }}</span>
                @endif
            </div>
        @endif

        <x-foundation-notice :title="__('Archive list connected')">{{ __('Draft creation and token-based verification are live. Search, filters, PDF upload, approval, and lifecycle actions remain separate tested slices.') }}</x-foundation-notice>

        <x-panel :padding="false">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center dark:border-slate-700">
                <div class="min-w-0 flex-1">
                    <flux:input icon="magnifying-glass" :placeholder="__('Search number, title, subject, or keyword...')" disabled />
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:flex lg:shrink-0">
                    <flux:select :placeholder="__('All types')" class="lg:w-40" disabled><flux:select.option>{{ __('All types') }}</flux:select.option></flux:select>
                    <flux:select :placeholder="__('All statuses')" class="lg:w-44" disabled><flux:select.option>{{ __('All statuses') }}</flux:select.option></flux:select>
                </div>
            </div>

            @if ($documents->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="data-table min-w-[920px]">
                        <thead>
                            <tr>
                                <th>{{ __('Document') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Verification') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                <tr wire:key="document-row-{{ $document->id }}">
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $document->document_number }}</div>
                                        <div class="mt-1 max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ $document->title }}</div>
                                    </td>
                                    <td>{{ $document->type->name }}</td>
                                    <td>{{ $document->category->name }}</td>
                                    <td>{{ $document->document_date->toFormattedDateString() }}</td>
                                    <td><x-status-badge :status="$document->status->value" /></td>
                                    <td>
                                        <flux:button size="sm" variant="outline" icon="qr-code" :href="$document->verificationUrl()" target="_blank">
                                            {{ __('Open') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                    {{ $documents->links() }}
                </div>
            @else
                <x-empty-state :title="__('No document records yet')" :description="__('Create a Draft record first. Its permanent verification identity is created before the final PDF is uploaded.')" icon="document-text">
                    <x-slot:action><flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Create first Draft') }}</flux:button></x-slot:action>
                </x-empty-state>
            @endif
        </x-panel>
    </div>
</x-layouts::app>
