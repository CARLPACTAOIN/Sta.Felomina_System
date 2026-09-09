@php
    $status = $document->status;
    $toneClasses = match ($status->tone()) {
        'amber' => 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-900/50 dark:text-amber-100 dark:ring-amber-900',
        'sky' => 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-900/50 dark:text-sky-100 dark:ring-sky-900',
        'red' => 'bg-red-100 text-red-800 ring-red-200 dark:bg-red-900/50 dark:text-red-100 dark:ring-red-900',
        'violet' => 'bg-violet-100 text-violet-800 ring-violet-200 dark:bg-violet-900/50 dark:text-violet-100 dark:ring-violet-900',
        'emerald' => 'bg-emerald-700 text-white ring-emerald-100 dark:bg-emerald-500 dark:text-emerald-950 dark:ring-emerald-900',
        default => 'bg-slate-200 text-slate-700 ring-slate-100 dark:bg-slate-700 dark:text-slate-100 dark:ring-slate-800',
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')</head>
    <body class="min-h-screen bg-[#f4f7f5] px-4 py-6 antialiased sm:py-10 dark:bg-slate-950">
        <main class="mx-auto max-w-3xl">
            <header class="mb-5 flex items-center justify-between gap-4">
                <x-app-logo href="{{ route('home') }}" />
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ __('Official verification') }}</span>
            </header>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_22px_65px_rgba(15,61,44,0.1)] dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-9 text-center sm:px-10 dark:border-slate-700 dark:bg-slate-800/40">
                    <span class="mx-auto flex size-16 items-center justify-center rounded-full ring-8 {{ $toneClasses }}"><flux:icon name="qr-code" class="size-8" /></span>
                    <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $status->verificationHeading() }}</h1>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $status->verificationDescription() }}</p>
                </div>

                <div class="p-6 sm:p-9">
                    @unless ($status->isPubliclyVerified())
                        <div class="mb-7 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            <flux:icon.information-circle class="mt-0.5 size-5 shrink-0" />
                            <p>{{ __('This page does not confirm authenticity. No official PDF is available for public viewing or download in this state.') }}</p>
                        </div>
                    @endunless

                    <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        @foreach ([['Document number', $document->document_number], ['Current status', $status->label()], ['Document type', $document->type->name], ['Title', $document->title], ['Primary category', $document->category->name], ['Issuing barangay', 'Barangay Sta. Felomina'], ['Verification reference', $document->verification_token]] as [$label, $value])
                            <div class="border-b border-slate-100 pb-4 dark:border-slate-800" wire:key="verification-field-{{ str($label)->slug() }}">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @unless ($status->isPubliclyVerified())
                        <p class="mt-7 text-center text-xs leading-5 text-slate-400">{{ __('Verification token found, but this record is not yet an approved public document.') }}</p>
                    @endunless
                </div>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
