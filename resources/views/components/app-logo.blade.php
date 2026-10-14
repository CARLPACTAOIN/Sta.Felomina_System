@props(['sidebar' => false])

<a {{ $attributes->class(['flex min-w-0 items-center gap-3 rounded-xl']) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-full shadow-[0_0_0_4px_rgba(255,255,255,0.08)]">
        <img src="{{ asset('images/barangay-logo.png') }}" alt="" class="size-11 object-contain" />
    </span>
    <span class="min-w-0">
        <strong class="block truncate text-sm font-bold tracking-[0.02em] {{ $sidebar ? 'text-white' : 'text-slate-950 dark:text-white' }}">Sta. Felomina</strong>
        <span class="mt-0.5 block truncate text-[11px] {{ $sidebar ? 'text-[#bad2c4]' : 'text-slate-500 dark:text-slate-400' }}">Records &amp; Verification</span>
    </span>
</a>
