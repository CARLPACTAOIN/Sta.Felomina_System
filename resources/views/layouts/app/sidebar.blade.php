<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f4f7f5] antialiased dark:bg-slate-950">
        @php
            $user = auth()->user();
            $unreadNotificationsCount = $user?->unreadNotifications()->count() ?? 0;
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="border-e-0! bg-[linear-gradient(180deg,#0b2d20,#0f3e2d_68%,#164933)]! text-white shadow-xl shadow-barangay-950/10">
            <flux:sidebar.header class="border-b border-white/10 pb-4">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-4">
                <flux:sidebar.group :heading="__('Workspace')" class="**:data-flux-sidebar-group-heading:text-emerald-200/60">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="bell"
                        :href="route('notifications.index')"
                        :current="request()->routeIs('notifications.*')"
                        :badge="$unreadNotificationsCount > 0 ? ($unreadNotificationsCount > 99 ? '99+' : (string) $unreadNotificationsCount) : null"
                        badge-color="red"
                        badge:variant="solid"
                        wire:navigate
                    >
                        {{ __('Notifications') }}
                    </flux:sidebar.item>

                    @if ($user->can('documents.view'))
                        <flux:sidebar.item icon="document-text" :href="route('documents.index')" :current="request()->routeIs('documents.*')" wire:navigate>
                            {{ __('Document Archive') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($user->can('documents.review'))
                        <flux:sidebar.item icon="inbox-arrow-down" :href="route('approvals.index')" :current="request()->routeIs('approvals.*')" wire:navigate>
                            {{ __('Pending Approvals') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="users" :href="route('residents.index')" :current="request()->routeIs('residents.*')" wire:navigate>
                        {{ $user->isPurokChairperson() ? __('Purok Residents') : __('Resident Directory') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('households.index')" :current="request()->routeIs('households.*')" wire:navigate>
                        {{ __('Households') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar-square" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ $user->isPurokChairperson() ? __('Purok Summary') : __('Reports') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if ($user->can('categorization-rules.manage') || $user->can('staff.manage') || $user->can('audit-logs.view'))
                    <flux:sidebar.group :heading="__('Administration')" class="mt-5 **:data-flux-sidebar-group-heading:text-emerald-200/60">
                        @if ($user->can('categorization-rules.manage'))
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('categorization-rules.index')" :current="request()->routeIs('categorization-rules.*')" wire:navigate>
                                {{ __('Categorization Rules') }}
                            </flux:sidebar.item>
                        @endif

                        @if ($user->can('staff.manage'))
                            <flux:sidebar.item icon="user-group" :href="route('staff-accounts.index')" :current="request()->routeIs('staff-accounts.*')" wire:navigate>
                                {{ __('Staff Accounts') }}
                            </flux:sidebar.item>
                        @endif

                        @if ($user->can('audit-logs.view'))
                            <flux:sidebar.item icon="clock" :href="route('audit-logs.index')" :current="request()->routeIs('audit-logs.*')" wire:navigate>
                                {{ __('Audit Logs') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if (Route::has('verification.preview'))
                    <flux:sidebar.group :heading="__('Preview')" class="mt-5 **:data-flux-sidebar-group-heading:text-emerald-200/60">
                        <flux:sidebar.item icon="qr-code" :href="route('verification.preview', ['state' => 'published'])" :current="request()->routeIs('verification.preview')" wire:navigate>
                            {{ __('Public Verification') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="mt-auto border-t border-white/10 pt-3">
                <x-desktop-user-menu />
            </div>
        </flux:sidebar>

        <flux:header class="sticky top-0 z-15 min-h-18.5 border-b border-[#e2e8e4] bg-white/88 px-4 backdrop-blur-md sm:px-8 dark:border-slate-800 dark:bg-slate-950/90">
            <div class="flex items-center gap-3">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                        {{ $title ?? __('Dashboard') }}
                    </h1>
                    <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">
                        Barangay Sta. Felomina • {{ now()->translatedFormat('l, F j, Y') }}
                    </div>
                </div>
            </div>

            <flux:spacer />

            <div class="flex items-center gap-2.5">
                <a
                    href="{{ route('notifications.index') }}"
                    wire:navigate
                    class="relative flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                    aria-label="{{ __('Notifications') }}"
                >
                    <flux:icon.bell class="size-4.5" />
                    @if ($unreadNotificationsCount > 0)
                        <span
                            data-testid="header-unread-badge"
                            class="absolute -top-1 -right-1 flex min-w-5 h-5 items-center justify-center rounded-full bg-[#d65a52] px-1 text-[10px] font-bold leading-none text-white shadow-xs"
                        >
                            {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                        </span>
                    @endif
                </a>
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
