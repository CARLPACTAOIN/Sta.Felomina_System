@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'hint' => null,
])

<article {{ $attributes->class(['rounded-2xl border border-slate-200/90 bg-white p-5 shadow-[0_8px_28px_rgba(15,61,44,0.04)] dark:border-slate-700 dark:bg-slate-900']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $value }}</p>
        </div>
        <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
            <flux:icon :name="$icon" class="size-5" />
        </span>
    </div>

    @if ($hint)
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</article>
