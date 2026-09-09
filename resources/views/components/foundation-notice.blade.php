@props([
    'title' => 'Interface foundation',
])

<div {{ $attributes->class(['flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100']) }}>
    <flux:icon.information-circle class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
    <div class="text-sm leading-6">
        <p class="font-semibold">{{ $title }}</p>
        <div class="text-amber-800 dark:text-amber-200">{{ $slot }}</div>
    </div>
</div>
