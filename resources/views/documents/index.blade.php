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

        <x-foundation-notice :title="__('Archive list connected')">{{ __('Draft creation, QR verification, approval workflow, category suggestions, and metadata filters are live. Report generation remains a separate tested slice.') }}</x-foundation-notice>

        <x-panel :padding="false">
            <form method="GET" action="{{ route('documents.index') }}" class="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(0,1fr)_repeat(5,minmax(120px,170px))_auto] lg:items-end dark:border-slate-700">
                <div class="min-w-0 flex-1">
                    <flux:field>
                        <flux:label>{{ __('Search') }}</flux:label>
                        <flux:input name="q" icon="magnifying-glass" :value="$filters['q']" :placeholder="__('Number, title, subject, keyword...')" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select name="type">
                        <flux:select.option value="" :selected="$filters['type'] === ''">{{ __('All types') }}</flux:select.option>
                        @foreach ($documentTypes as $documentType)
                            <flux:select.option :value="$documentType->id" :selected="(string) $filters['type'] === (string) $documentType->id" wire:key="document-filter-type-{{ $documentType->id }}">{{ $documentType->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Category') }}</flux:label>
                    <flux:select name="category">
                        <flux:select.option value="" :selected="$filters['category'] === ''">{{ __('All categories') }}</flux:select.option>
                        @foreach ($documentCategories as $documentCategory)
                            <flux:select.option :value="$documentCategory->id" :selected="(string) $filters['category'] === (string) $documentCategory->id" wire:key="document-filter-category-{{ $documentCategory->id }}">{{ $documentCategory->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select name="status">
                        <flux:select.option value="" :selected="$filters['status'] === ''">{{ __('All statuses') }}</flux:select.option>
                        @foreach ($statuses as $status)
                            <flux:select.option :value="$status->value" :selected="$filters['status'] === $status->value" wire:key="document-filter-status-{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Year') }}</flux:label>
                    <flux:input name="year" type="number" min="1900" max="2100" :value="$filters['year']" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Sort') }}</flux:label>
                    <flux:select name="sort">
                        @foreach ($sortOptions as $value => $label)
                            <flux:select.option :value="$value" :selected="$filters['sort'] === $value" wire:key="document-sort-{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit" icon="funnel">{{ __('Filter') }}</flux:button>
                    <flux:button variant="outline" :href="route('documents.index')">{{ __('Reset') }}</flux:button>
                </div>
            </form>

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
                                <th>{{ __('Actions') }}</th>
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
                                        <div class="flex gap-2">
                                            <flux:button size="sm" variant="outline" icon="eye" :href="route('documents.show', $document)" wire:navigate>
                                                {{ __('Manage') }}
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" icon="qr-code" :href="$document->verificationUrl()" target="_blank">
                                                {{ __('Verify') }}
                                            </flux:button>
                                        </div>
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
