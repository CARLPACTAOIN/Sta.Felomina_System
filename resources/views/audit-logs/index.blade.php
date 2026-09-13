<x-layouts::app :title="__('Audit Logs')">
    <div class="app-content">
        <x-page-header :title="__('Audit Logs')" :description="__('Review immutable application-generated history for important document workflow events.')" />

        <x-panel :title="__('Recent audit events')" :description="__('Only system-generated entries are shown. Manual creation and editing are intentionally unavailable.')" :padding="false">
            @if ($auditLogs->isEmpty())
                <x-empty-state :title="__('No audit events yet')" :description="__('Submission, return, and approval events will appear here with actor, record, and timestamp.')" icon="clock" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">{{ __('Time') }}</th>
                                <th class="px-4 py-3">{{ __('Actor') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                                <th class="px-4 py-3">{{ __('Record') }}</th>
                                <th class="px-4 py-3">{{ __('Summary') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($auditLogs as $auditLog)
                                <tr>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $auditLog->created_at?->toDayDateTimeString() }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $auditLog->actor?->name ?? __('System') }}</td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ str($auditLog->action)->headline() }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ class_basename($auditLog->auditable_type) }} #{{ $auditLog->auditable_id }}</td>
                                    <td class="px-4 py-4 text-slate-900 dark:text-white">{{ $auditLog->summary }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts::app>
