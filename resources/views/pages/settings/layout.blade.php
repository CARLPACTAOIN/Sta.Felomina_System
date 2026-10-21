<div class="settings-light overflow-hidden rounded-3xl border border-slate-200/90 bg-white p-2 shadow-[0_18px_55px_rgba(15,61,44,0.08)] sm:p-3">
    <div class="grid gap-3 lg:grid-cols-[248px_minmax(0,1fr)] lg:items-start">
        <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-[#dbe8df] bg-[#eef5f0] p-3 text-slate-700 shadow-sm shadow-emerald-950/5 lg:sticky lg:top-6 lg:grid lg:gap-1.5 lg:overflow-visible lg:p-4" aria-label="{{ __('Account settings') }}">
            <div class="hidden border-b border-[#dbe8df] pb-4 lg:block">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-barangay-700">{{ __('Account center') }}</p>
                <p class="mt-1 text-sm font-semibold tracking-tight text-slate-900">{{ __('Your preferences') }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('Update your profile, sign-in protection, and display choices.') }}</p>
            </div>

            <div class="flex shrink-0 gap-2 lg:mt-2 lg:grid lg:gap-1.5">
                @foreach ([
                    ['profile.edit', 'user', __('Profile'), __('Personal details')],
                    ['security.edit', 'shield-check', __('Security'), __('Password and sign-in')],
                    ['appearance.edit', 'computer-desktop', __('Appearance'), __('Display preference')],
                ] as [$routeName, $icon, $label, $description])
                    <a
                        href="{{ route($routeName) }}"
                        wire:navigate
                        @class([
                            'group flex min-w-40 shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-left transition duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-barangay-700 lg:min-w-0',
                            'bg-white text-barangay-950 shadow-sm ring-1 ring-barangay-200' => request()->routeIs($routeName),
                            'text-slate-600 hover:bg-white/70 hover:text-slate-950' => ! request()->routeIs($routeName),
                        ])
                        wire:key="settings-nav-{{ $routeName }}"
                    >
                        <span @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-lg transition',
                            'bg-barangay-100 text-barangay-800' => request()->routeIs($routeName),
                            'bg-white/80 text-slate-500 group-hover:bg-barangay-50 group-hover:text-barangay-800' => ! request()->routeIs($routeName),
                        ])>
                            <flux:icon :name="$icon" class="size-4.5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold leading-5">{{ $label }}</span>
                            <span @class([
                                'mt-0.5 hidden text-xs leading-4 lg:block',
                                'text-slate-500' => request()->routeIs($routeName),
                                'text-slate-500' => ! request()->routeIs($routeName),
                            ])>{{ $description }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="hidden rounded-xl border border-[#dbe8df] bg-white/60 px-3 py-2.5 lg:block">
                <p class="text-xs font-medium text-slate-700">{{ __('Need help?') }}</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">{{ __('Ask your Barangay Captain for account assistance.') }}</p>
            </div>
        </nav>

        <div class="min-w-0 rounded-2xl bg-[#f7faf8] p-5 sm:p-7 lg:p-8">
            <div class="mb-7 border-b border-slate-200/80 pb-5">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-barangay-700">{{ __('Account preferences') }}</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $heading ?? '' }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $subheading ?? '' }}</p>
            </div>
            <div class="grid gap-5">{{ $slot }}</div>
        </div>
    </div>
</div>
