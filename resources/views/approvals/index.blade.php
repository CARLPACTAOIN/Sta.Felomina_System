<x-layouts::app :title="__('Pending Approvals')">
    <div class="app-content">
        <x-page-header
            :title="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Pending Approvals') : __('Approval Queue')"
            :description="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Review submitted metadata and final PDFs before publication.') : __('View documents currently awaiting Captain review.')"
        />

        <x-panel :title="__('Pending Approval documents')" :description="__('Records are ordered by most recent submission.')" :padding="false">
            @if ($documents->isEmpty())
                <x-empty-state
                    :title="__('Nothing awaiting review')"
                    :description="__('Submitted documents will appear here after staff submit Draft or Needs Revision records for Captain approval.')"
                    icon="inbox-arrow-down"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">{{ __('Document') }}</th>
                                <th class="px-4 py-3">{{ __('Type / Category') }}</th>
                                <th class="px-4 py-3">{{ __('Submitted') }}</th>
                                <th class="px-4 py-3">{{ __('Created by') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($documents as $document)
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $document->document_number }}</p>
                                        <p class="mt-1 text-slate-500 dark:text-slate-400">{{ $document->title }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                        <p>{{ $document->type->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $document->category->name }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                        <p>{{ $document->submitted_at?->toDayDateTimeString() ?? __('Not recorded') }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $document->submitter?->name ?? __('Unknown staff') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $document->creator->name }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <flux:button size="sm" variant="primary" :href="route('documents.show', $document)" wire:navigate>{{ __('Review') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                    {{ $documents->links() }}
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts::app>
