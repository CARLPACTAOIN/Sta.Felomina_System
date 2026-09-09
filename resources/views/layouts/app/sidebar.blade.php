<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f4f7f5] antialiased dark:bg-slate-950">
        @php($role = auth()->user()->role)

        <flux:sidebar sticky collapsible="mobile" class="border-e-0! bg-barangay-950! text-white shadow-xl shadow-barangay-950/10">
            <flux:sidebar.header class="border-b border-white/10 pb-4">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-4">
                <flux:sidebar.group :heading="__('Workspace')" class="[&_[data-flux-sidebar-group-heading]]:text-emerald-200/60">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    @if ($role->canManageDocuments())
                        <flux:sidebar.item icon="document-text" :href="route('documents.index')" :current="request()->routeIs('documents.*')" wire:navigate>
                            {{ __('Document Archive') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="inbox-arrow-down" :href="route('approvals.index')" :current="request()->routeIs('approvals.*')" wire:navigate>
                            {{ $role === \App\Enums\UserRole::Captain ? __('Pending Approvals') : __('Approval Status') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="users" :href="route('residents.index')" :current="request()->routeIs('residents.*')" wire:navigate>
                        {{ $role === \App\Enums\UserRole::PurokChairperson ? __('Purok Residents') : __('Resident Directory') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('households.index')" :current="request()->routeIs('households.*')" wire:navigate>
                        {{ __('Households') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar-square" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ $role === \App\Enums\UserRole::PurokChairperson ? __('Purok Summary') : __('Reports') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if ($role->canManageDocuments() || $role === \App\Enums\UserRole::Captain)
                    <flux:sidebar.group :heading="__('Administration')" class="mt-5 [&_[data-flux-sidebar-group-heading]]:text-emerald-200/60">
                        @if ($role->canManageDocuments())
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('categorization-rules.index')" :current="request()->routeIs('categorization-rules.*')" wire:navigate>
                                {{ __('Categorization Rules') }}
                            </flux:sidebar.item>
                        @endif

                        @if ($role === \App\Enums\UserRole::Captain)
                            <flux:sidebar.item icon="user-group" :href="route('staff-accounts.index')" :current="request()->routeIs('staff-accounts.*')" wire:navigate>
                                {{ __('Staff Accounts') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="clock" :href="route('audit-logs.index')" :current="request()->routeIs('audit-logs.*')" wire:navigate>
                                {{ __('Audit Logs') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav class="border-t border-white/10 pt-4">
                <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>
                    {{ __('Notifications') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                    {{ __('My Account') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        <flux:header class="border-b border-slate-200/80 bg-white/90 backdrop-blur lg:hidden dark:border-slate-800 dark:bg-slate-950/90">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ config('app.name') }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $role->label() }}</p>
            </div>
            <flux:spacer />
            <flux:button variant="ghost" size="sm" icon="bell" :href="route('notifications.index')" wire:navigate :aria-label="__('Notifications')" />
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
