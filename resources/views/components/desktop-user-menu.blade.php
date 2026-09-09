<flux:dropdown position="top" align="start">
    <button type="button" {{ $attributes->class(['flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[0.06] p-3 text-left transition hover:bg-white/10']) }} data-test="sidebar-menu-button">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-barangay-900">
            {{ auth()->user()->initials() }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-xs font-semibold text-white">{{ auth()->user()->name }}</span>
            <span class="mt-0.5 block truncate text-[0.68rem] text-emerald-100/65">{{ auth()->user()->role->label() }}</span>
        </span>
        <flux:icon.chevrons-up-down class="size-4 text-emerald-100/60" />
    </button>

    <flux:menu>
        <div class="px-2 py-2">
            <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>{{ __('My Account') }}</flux:menu.item>
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
