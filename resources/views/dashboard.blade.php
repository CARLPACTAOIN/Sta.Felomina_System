<x-layouts::app :title="__('Dashboard')">
    <div class="app-content">
        <x-page-header
            :eyebrow="$isPurokChairperson ? __('Assigned purok workspace') : __('Barangay-wide workspace')"
            :title="__('Good day, :name', ['name' => str(auth()->user()->name)->before(' ')])"
            :description="$isPurokChairperson
                ? __('Maintain residents and households for :purok.', ['purok' => $metrics['purok_name']])
                : __('Review the records, pending approvals, and community statistics for Barangay Sta. Felomina.')"
        >
            <x-slot:actions>
                @if (auth()->user()->can('documents.review') || auth()->user()->can('documents.create'))
                    <div class="flex flex-wrap items-center gap-2">
                        @if (auth()->user()->can('documents.review'))
                            <flux:button variant="outline" icon="inbox-arrow-down" :href="route('approvals.index')" wire:navigate>{{ __('Review approvals') }}</flux:button>
                        @endif
                        @if (auth()->user()->can('documents.create'))
                            <flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Create document record') }}</flux:button>
                        @endif
                    </div>
                @else
                    <flux:button variant="primary" icon="user-plus" :href="route('residents.index')" wire:navigate>{{ __('Open resident directory') }}</flux:button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($isPurokChairperson)
                <x-metric-card :label="__('Residents')" :value="$metrics['total_residents']" icon="users" :hint="$metrics['purok_name']" />
                <x-metric-card :label="__('Active residents')" :value="$metrics['active_residents']" icon="user-group" :hint="__('Status-based count')" />
                <x-metric-card :label="__('Households')" :value="$metrics['households']" icon="home-modern" :hint="$metrics['purok_name']" />
                <x-metric-card :label="__('Recent updates')" :value="$metrics['recent_updates']" icon="clock" :hint="__('Last seven days')" />
            @else
                <x-metric-card :label="__('Document records')" :value="$metrics['total_documents']" icon="document-text" :hint="__(':published published', ['published' => $metrics['published_documents']])" />
                <x-metric-card :label="__('Pending approval')" :value="$metrics['pending_approvals']" icon="inbox-arrow-down" :hint="__('Captain review queue')" />
                <x-metric-card :label="__('Published')" :value="$metrics['published_documents']" icon="shield-check" :hint="__('Publicly verified')" />
                <x-metric-card :label="__('Residents')" :value="$metrics['total_residents']" icon="users" :hint="__('Barangay-wide')" />
            @endif
        </div>

        @if (! $isPurokChairperson)
            <div class="grid gap-5 md:grid-cols-2">
                <x-panel :title="__('Documents by Category')" :description="__('Distribution of official archived records by assigned category.')">
                    @if ($metrics['category_distribution']->isNotEmpty())
                        <div class="grid gap-3">
                            @foreach ($metrics['category_distribution'] as $cat)
                                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-900/60" wire:key="cat-dist-{{ $loop->index }}">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $cat['name'] }}</span>
                                    <span class="inline-flex items-center rounded-lg bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        {{ $cat['count'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No category data available yet.') }}</p>
                    @endif
                </x-panel>

                <x-panel :title="__('Residents by Purok')" :description="__('Population count across all 7 puroks of Barangay Sta. Felomina.')">
                    @if ($metrics['purok_distribution']->isNotEmpty())
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($metrics['purok_distribution'] as $purok)
                                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-900/60" wire:key="purok-dist-{{ $loop->index }}">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $purok['name'] }}</span>
                                    <span class="inline-flex items-center rounded-lg bg-slate-200/80 px-2.5 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $purok['count'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No resident data available yet.') }}</p>
                    @endif
                </x-panel>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
            <x-panel :title="$isPurokChairperson ? __('Recent resident updates') : __('Recent audit activity')" :description="__('Latest authorized changes and recorded events.')" :padding="false">
                @if ($isPurokChairperson)
                    @if ($metrics['recent_residents']->isNotEmpty())
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($metrics['recent_residents'] as $resident)
                                <div class="flex items-center justify-between px-5 py-3.5 transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" wire:key="recent-res-{{ $resident->id }}">
                                    <div class="min-w-0 pr-4">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ $resident->last_name }}, {{ $resident->first_name }} {{ $resident->middle_name }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $resident->resident_code }} • {{ $resident->household?->household_code ?? __('No household') }} • {{ $resident->address }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $resident->updated_at?->diffForHumans() }}</span>
                                        <x-status-badge :status="$resident->status" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state
                            :title="__('No resident records yet')"
                            :description="__('Resident creation and status changes for :purok will appear here.', ['purok' => $metrics['purok_name']])"
                            icon="users"
                        />
                    @endif
                @else
                    @if ($metrics['recent_activity']->isNotEmpty())
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($metrics['recent_activity'] as $log)
                                <div class="flex items-start justify-between px-5 py-3.5 transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40" wire:key="recent-log-{{ $log->id }}">
                                    <div class="min-w-0 pr-4">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $log->summary }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $log->actor?->name ?? __('System') }} • <span class="font-mono">{{ $log->action }}</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                        {{ $log->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state
                            :title="__('No activity yet')"
                            :description="__('Document, approval, and resident events will appear here automatically.')"
                            icon="clock"
                        />
                    @endif
                @endif
            </x-panel>

            <x-panel :title="__('Workflow guide')" :description="__('The product sequence defined by the PRD.')">
                @if ($isPurokChairperson)
                    <ol class="grid gap-4">
                        @foreach ([
                            ['Search first', 'Check for an existing resident before creating a new record.'],
                            ['Confirm household', 'Reuse household information instead of repeating the same address.'],
                            ['Maintain status', 'Use Active, Transferred, Deceased, or Inactive rather than deleting history.'],
                        ] as [$title, $description])
                            <li class="flex gap-3" wire:key="purok-guide-{{ str($title)->slug() }}">
                                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ $loop->iteration }}</span>
                                <div><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p><p class="mt-0.5 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <ol class="grid gap-4">
                        @foreach ([
                            ['Create Draft Record', 'Enter metadata before a final PDF exists.'],
                            ['Use Permanent QR', 'Download and place the stable verification identity in the document.'],
                            ['Upload Final PDF', 'Attach the prepared official PDF before submission.'],
                            ['Captain Review', 'Approve or return with clear revision comments.'],
                        ] as [$title, $description])
                            <li class="flex gap-3" wire:key="document-guide-{{ str($title)->slug() }}">
                                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ $loop->iteration }}</span>
                                <div><p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p><p class="mt-0.5 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-panel>
        </div>
    </div>
</x-layouts::app>
