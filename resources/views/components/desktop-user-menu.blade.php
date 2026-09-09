<flux:dropdown position="top" align="start" class="w-full">
    <button
        type="button"
        {{ $attributes->class([
            'group flex w-full items-center gap-2.5 rounded-[13px] border border-white/[0.08] bg-white/[0.07] p-2.5 text-left transition hover:bg-white/[0.12] focus:outline-hidden focus:ring-2 focus:ring-white/20'
        ]) }}
        data-test="sidebar-menu-button"
    >
        <span class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-[#d8e8de] text-xs font-black text-[#103b2b]">
            {{ auth()->user()->initials() }}
        </span>
        <span class="min-w-0 flex-1">
            <strong class="block truncate text-xs font-semibold text-white group-hover:text-white">
                {{ auth()->user()->name }}
            </strong>
            <span class="block truncate text-[10px] text-[#adc8b8]">
                {{ auth()->user()->role->label() }}
            </span>
        </span>
        <flux:icon.chevrons-up-down class="size-4 shrink-0 text-[#90b29f] transition group-hover:text-white" />
    </button>

    <flux:menu class="w-56">
        <div class="px-3 py-2.5">
            <p class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
            <span class="mt-1 inline-block rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                {{ auth()->user()->role->label() }}
            </span>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('Account Settings') }}
        </flux:menu.item>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer text-rose-600 dark:text-rose-400" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
