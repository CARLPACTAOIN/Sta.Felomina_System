@props(['status'])

@php
    $normalizedStatus = str($status)->lower()->replace(' ', '-')->toString();
    $classes = match ($normalizedStatus) {
        'published', 'active', 'verified' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'pending', 'pending-approval' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'needs-revision', 'revoked' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'archived' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        'superseded' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', $classes]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ str($status)->headline() }}
</span>
