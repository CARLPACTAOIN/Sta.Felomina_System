@props([
    'title' => null,
    'description' => null,
    'padding' => true,
    'light' => false,
])

<section {{ $attributes->class([
    'overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_10px_35px_rgba(15,61,44,0.045)]',
    'dark:border-slate-700 dark:bg-slate-900' => ! $light,
]) }}>
    @if ($title || $description || isset($actions))
        <header @class([
            'flex flex-col gap-3 border-b border-slate-200/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
            'dark:border-slate-700' => ! $light,
        ])>
            <div>
                @if ($title)
                    <h2 @class(['text-sm font-semibold text-slate-950', 'dark:text-white' => ! $light])>{{ $title }}</h2>
                @endif
                @if ($description)
                    <p @class(['mt-0.5 text-xs leading-5 text-slate-500', 'dark:text-slate-400' => ! $light])>{{ $description }}</p>
                @endif
            </div>

            @if (isset($actions))
                <div class="shrink-0">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <div @class(['p-5 sm:p-6' => $padding])>{{ $slot }}</div>
</section>
