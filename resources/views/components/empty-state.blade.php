@props([
    'title',
    'description',
    'icon' => 'inbox',
])

<div {{ $attributes->class(['flex flex-col items-center px-5 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
        <flux:icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-slate-950 dark:text-white">{{ $title }}</h3>
    <p class="mt-1 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @if (isset($action))
        <div class="mt-5">{{ $action }}</div>
    @endif
</div>
