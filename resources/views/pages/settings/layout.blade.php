<div class="grid gap-5 lg:grid-cols-[210px_minmax(0,1fr)] lg:items-start">
    <nav class="flex gap-2 overflow-x-auto rounded-xl border border-slate-200 bg-white p-2 lg:sticky lg:top-6 lg:grid dark:border-slate-700 dark:bg-slate-900" aria-label="{{ __('Settings') }}">
        @foreach ([
            ['profile.edit', 'user', __('Profile')],
            ['security.edit', 'shield-check', __('Security')],
            ['appearance.edit', 'computer-desktop', __('Appearance')],
        ] as [$routeName, $icon, $label])
            <a
                href="{{ route($routeName) }}"
                wire:navigate
                @class([
                    'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700',
                    'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100' => request()->routeIs($routeName),
                    'text-slate-600 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! request()->routeIs($routeName),
                ])
                wire:key="settings-nav-{{ $routeName }}"
            >
                <flux:icon :name="$icon" class="size-4" />
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="min-w-0">
        <div class="mb-5">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">{{ $heading ?? '' }}</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $subheading ?? '' }}</p>
        </div>
        <div class="grid gap-5">{{ $slot }}</div>
    </div>
</div>
