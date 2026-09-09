@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<header {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-400">{{ $eyebrow }}</p>
        @endif

        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-[1.7rem] dark:text-white">{{ $title }}</h1>

        @if ($description)
            <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</header>
