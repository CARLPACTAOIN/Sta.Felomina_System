<x-layouts::app :title="__('Audit Logs')">
    <div class="app-content">
        <x-page-header
            :title="__('Audit Logs')"
            :description="__('Review immutable application-generated history for documents, residents, households, categorization rules, and staff accounts.')"
        >
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <flux:button variant="outline" icon="printer" :href="route('audit-logs.print', array_merge(request()->query(), ['auto_print' => 1]))" target="_blank">
                        {{ __('Print / Save as PDF') }}
                    </flux:button>
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('audit-logs.export', request()->query())">
                        {{ __('Export CSV') }}
                    </flux:button>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-panel :title="__('Audit Trail (:count events)', ['count' => number_format($totalCount)])" :description="__('Only system-generated entries are shown. Manual creation and editing are strictly prohibited.')" :padding="false">
            {{-- Filter Form --}}
            <form method="GET" action="{{ route('audit-logs.index') }}" class="border-b border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 lg:items-end">
                    <div class="sm:col-span-2 lg:col-span-1 xl:col-span-2">
                        <flux:field>
                            <flux:label>{{ __('Search keyword') }}</flux:label>
                            <flux:input name="q" icon="magnifying-glass" :value="$filters['q']" :placeholder="__('Summary, action, actor, record ID...')" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Actor') }}</flux:label>
                            <flux:select name="actor_id">
                                <flux:select.option value="" :selected="$filters['actor_id'] === ''">{{ __('All actors') }}</flux:select.option>
                                <flux:select.option value="system" :selected="$filters['actor_id'] === 'system'">{{ __('System (Automated)') }}</flux:select.option>
                                @foreach ($actors as $actor)
                                    <flux:select.option :value="$actor->id" :selected="(string) $filters['actor_id'] === (string) $actor->id">{{ $actor->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Action type') }}</flux:label>
                            <flux:select name="action">
                                <flux:select.option value="" :selected="$filters['action'] === ''">{{ __('All actions') }}</flux:select.option>
                                @foreach ($actions as $actionValue => $actionLabel)
                                    <flux:select.option :value="$actionValue" :selected="$filters['action'] === $actionValue">{{ $actionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Record type') }}</flux:label>
                            <flux:select name="entity_type">
                                <flux:select.option value="" :selected="$filters['entity_type'] === ''">{{ __('All record types') }}</flux:select.option>
                                @foreach ($entityTypes as $typeClass => $typeLabel)
                                    <flux:select.option :value="$typeClass" :selected="$filters['entity_type'] === $typeClass || \App\Models\AuditLog::normalizeEntityType($filters['entity_type']) === $typeClass">{{ $typeLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Date from') }}</flux:label>
                            <flux:input type="date" name="date_from" :value="$filters['date_from']" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Date to') }}</flux:label>
                            <flux:input type="date" name="date_to" :value="$filters['date_to']" />
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>{{ __('Sort order') }}</flux:label>
                            <flux:select name="sort">
                                <flux:select.option value="newest" :selected="$filters['sort'] !== 'oldest'">{{ __('Newest first') }}</flux:select.option>
                                <flux:select.option value="oldest" :selected="$filters['sort'] === 'oldest'">{{ __('Oldest first') }}</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-2 xl:col-span-4">
                        <flux:button variant="primary" type="submit" icon="funnel">{{ __('Apply filters') }}</flux:button>
                        <flux:button variant="outline" :href="route('audit-logs.index')" icon="arrow-path">{{ __('Reset') }}</flux:button>
                    </div>
                </div>
            </form>

            {{-- Active Filter Pills Strip --}}
            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-emerald-50/60 px-4 py-2.5 text-xs text-emerald-950 dark:border-slate-700 dark:bg-emerald-950/20 dark:text-emerald-100">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-semibold">{{ __('Active filters:') }}</span>
                        @if ($filters['q'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">{{ __('Keyword:') }} "{{ $filters['q'] }}"</span>
                        @endif
                        @if ($filters['actor_id'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                {{ __('Actor:') }} {{ $filters['actor_id'] === 'system' ? __('System') : ($actors->firstWhere('id', $filters['actor_id'])?->name ?? '#'.$filters['actor_id']) }}
                            </span>
                        @endif
                        @if ($filters['action'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">{{ __('Action:') }} {{ $actions[$filters['action']] ?? $filters['action'] }}</span>
                        @endif
                        @if ($filters['entity_type'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                {{ __('Record:') }} {{ $entityTypes[\App\Models\AuditLog::normalizeEntityType($filters['entity_type']) ?? ''] ?? $filters['entity_type'] }}
                            </span>
                        @endif
                        @if ($filters['date_from'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">{{ __('From:') }} {{ $filters['date_from'] }}</span>
                        @endif
                        @if ($filters['date_to'] !== '')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">{{ __('To:') }} {{ $filters['date_to'] }}</span>
                        @endif
                    </div>
                    <a href="{{ route('audit-logs.index') }}" class="font-semibold text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300">
                        {{ __('Clear all') }}
                    </a>
                </div>
            @endif

            @if ($auditLogs->isEmpty())
                @if ($hasActiveFilters)
                    <x-empty-state :title="__('No matching audit events')" :description="__('No audit log entries match your active search and filter criteria. Try broadening your parameters or resetting them.')" icon="magnifying-glass">
                        <x-slot:action>
                            <flux:button variant="outline" :href="route('audit-logs.index')">{{ __('Reset all filters') }}</flux:button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-empty-state :title="__('No audit events yet')" :description="__('Submission, return, and approval events will appear here with actor, record, and timestamp.')" icon="clock" />
                @endif
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">{{ __('Time') }}</th>
                                <th class="px-4 py-3">{{ __('Actor') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                                <th class="px-4 py-3">{{ __('Record') }}</th>
                                <th class="px-4 py-3">{{ __('Summary & Context') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($auditLogs as $auditLog)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $auditLog->created_at?->format('M d, Y') }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $auditLog->created_at?->format('h:i:s A') }} <span class="text-slate-400">({{ $auditLog->created_at?->diffForHumans() }})</span></div>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $auditLog->actor?->name ?? __('System') }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $auditLog->actor?->staffRoleLabel() ?? __('Automated Service') }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $auditLog->actionBadgeClasses() }}">
                                            {{ $auditLog->actionLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                                        @if ($auditLog->entityRoute())
                                            <a href="{{ $auditLog->entityRoute() }}" class="inline-flex items-center gap-1 font-semibold text-emerald-700 hover:underline dark:text-emerald-400">
                                                <span>{{ $auditLog->entityLabel() }}</span>
                                                <span class="font-mono text-xs text-slate-500 dark:text-slate-400">#{{ $auditLog->auditable_id }}</span>
                                            </a>
                                        @else
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $auditLog->entityLabel() }}</span>
                                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400">#{{ $auditLog->auditable_id }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $auditLog->summary }}</div>
                                        @if ($auditLog->context)
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                                @if (isset($auditLog->context['reason']))
                                                    <span class="rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                        <strong>{{ __('Reason:') }}</strong> {{ $auditLog->context['reason'] }}
                                                    </span>
                                                @endif
                                                @if (isset($auditLog->context['comment']))
                                                    <span class="rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                        <strong>{{ __('Comment:') }}</strong> {{ $auditLog->context['comment'] }}
                                                    </span>
                                                @endif
                                                @if (isset($auditLog->context['changed_fields']) && is_array($auditLog->context['changed_fields']))
                                                    <span class="text-slate-500">{{ __('Fields:') }}</span>
                                                    @foreach ($auditLog->context['changed_fields'] as $field)
                                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $field }}</span>
                                                    @endforeach
                                                @endif
                                                @if (isset($auditLog->context['version_number']))
                                                    <span class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        v{{ $auditLog->context['version_number'] }}
                                                    </span>
                                                @endif
                                                @if (isset($auditLog->context['old_status']) && isset($auditLog->context['new_status']))
                                                    <span class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ $auditLog->context['old_status'] }} &rarr; {{ $auditLog->context['new_status'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
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
