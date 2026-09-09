<x-layouts::app :title="__('Dashboard')">
    @php($isPurokChairperson = auth()->user()->role === \App\Enums\UserRole::PurokChairperson)

    <div class="app-content">
        <x-page-header
            :eyebrow="$isPurokChairperson ? __('Assigned purok workspace') : __('Barangay-wide workspace')"
            :title="__('Good day, :name', ['name' => str(auth()->user()->name)->before(' ')])"
            :description="$isPurokChairperson
                ? __('Maintain residents and households for your assigned purok.')
                : __('Review the records and tasks that need your attention today.')"
        >
            <x-slot:actions>
                @if (auth()->user()->role->canManageDocuments())
                    <flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Create document record') }}</flux:button>
                @else
                    <flux:button variant="primary" icon="user-plus" :href="route('residents.index')" wire:navigate>{{ __('Open resident directory') }}</flux:button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-foundation-notice>
            {{ __('The operational dashboard is ready for domain data. Counts remain empty until the document and resident modules are implemented; no prototype statistics are presented as real records.') }}
        </x-foundation-notice>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($isPurokChairperson)
                <x-metric-card :label="__('Residents')" value="0" icon="users" :hint="__('Assigned purok only')" />
                <x-metric-card :label="__('Active residents')" value="0" icon="user-group" :hint="__('Status-based count')" />
                <x-metric-card :label="__('Households')" value="0" icon="home-modern" :hint="__('Assigned purok only')" />
                <x-metric-card :label="__('Recent updates')" value="0" icon="clock" :hint="__('Last seven days')" />
            @else
                <x-metric-card :label="__('Document records')" value="0" icon="document-text" :hint="__('All lifecycle states')" />
                <x-metric-card :label="__('Pending approval')" value="0" icon="inbox-arrow-down" :hint="__('Captain review queue')" />
                <x-metric-card :label="__('Published')" value="0" icon="shield-check" :hint="__('Publicly verified records')" />
                <x-metric-card :label="__('Residents')" value="0" icon="users" :hint="__('Barangay-wide')" />
            @endif
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
            <x-panel :title="$isPurokChairperson ? __('Recent resident activity') : __('Recent record activity')" :description="__('Latest authorized changes will appear here automatically.')" :padding="false">
                <x-empty-state
                    :title="__('No activity yet')"
                    :description="$isPurokChairperson
                        ? __('Resident creation and status changes for your assigned purok will be listed here.')
                        : __('Document, approval, and resident events will be listed here after the domain modules are connected.')"
                    icon="clock"
                />
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
