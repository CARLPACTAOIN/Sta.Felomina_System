@props(['sidebar' => false])

<a {{ $attributes->class(['flex min-w-0 items-center gap-3 rounded-xl']) }}>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-300 text-[0.65rem] font-black tracking-tight text-barangay-950 ring-4 ring-white/10">
        BSF
    </span>
    <span class="min-w-0">
        <span class="block truncate text-sm font-bold {{ $sidebar ? 'text-white' : 'text-slate-950 dark:text-white' }}">Barangay Sta. Felomina</span>
        <span class="mt-0.5 block truncate text-[0.68rem] {{ $sidebar ? 'text-emerald-100/70' : 'text-slate-500 dark:text-slate-400' }}">Records & Verification</span>
    </span>
</a>
