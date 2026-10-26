@php
    $status = $document->status;
    $currentVersion = $document->currentFileVersion();
    $publicPdfAvailable = $document->hasPublicPdfAccess() && $currentVersion !== null;
    $hasApprovedMetadata = $document->approved_at !== null;
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
                    @if (! $hasApprovedMetadata)
                        <div class="mb-7 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            <flux:icon.information-circle class="mt-0.5 size-5 shrink-0" />
                            <p>{{ __('This page does not confirm authenticity. No official PDF is available for public viewing or download in this state.') }}</p>
                        </div>
                    @elseif ($status === \App\Enums\DocumentStatus::Archived)
                        <div class="mb-7 flex gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100">
                            <flux:icon.information-circle class="mt-0.5 size-5 shrink-0" />
                            <p>{{ __('This document remains historically valid, but it is no longer the current active record.') }}</p>
                        </div>
                    @elseif (in_array($status, [\App\Enums\DocumentStatus::Revoked, \App\Enums\DocumentStatus::Superseded], true))
                        <div class="mb-7 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100">
                            <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                            <p>{{ __('This verification token is valid, but the document is not a current valid public document. No official PDF is available for public viewing or download in this state.') }}</p>
                        </div>
                    @endif

                    <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        @foreach ([['Document number', $document->document_number], ['Current status', $status->label()], ['Document type', $document->type->name], ['Title', $document->title], ['Primary category', $document->category->name], ['Issuing barangay', 'Barangay Sta. Felomina'], ['Verification reference', $document->verification_token], ['Approved date', $hasApprovedMetadata ? $document->approved_at?->toFormattedDateString() : __('Not approved')], ['Approved by', $hasApprovedMetadata ? ($document->approver?->name ?? __('Barangay Captain')) : __('Not approved')]] as [$label, $value])
                            <div class="min-w-0 border-b border-slate-100 pb-4 dark:border-slate-800" wire:key="verification-field-{{ str($label)->slug() }}">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt>
                                <dd @class([
                                    'mt-1.5 text-sm font-semibold text-slate-900 dark:text-white',
                                    'wrap-anywhere font-mono text-xs leading-5' => $label === 'Verification reference',
                                ])>{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($document->is_project_related && filled($document->project_name))
                            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Project name') }}</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $document->project_name }}</dd>
                            </div>
                        @endif
                        @if ($document->is_project_related && filled($document->project_location))
                            <div class="border-b border-slate-100 pb-4 dark:border-slate-800">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Project location') }}</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">{{ $document->project_location }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if (in_array($status, [\App\Enums\DocumentStatus::Archived, \App\Enums\DocumentStatus::Revoked, \App\Enums\DocumentStatus::Superseded], true))
                        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                            @if ($status === \App\Enums\DocumentStatus::Archived)
                                <p class="font-semibold text-slate-950 dark:text-white">{{ __('Archived :date by :name', ['date' => $document->archived_at?->toFormattedDateString(), 'name' => $document->archiver?->name ?? __('Barangay Captain')]) }}</p>
                                <p class="mt-2 leading-6">{{ $document->archive_reason }}</p>
                            @elseif ($status === \App\Enums\DocumentStatus::Revoked)
                                <p class="font-semibold text-slate-950 dark:text-white">{{ __('Revoked :date by :name', ['date' => $document->revoked_at?->toFormattedDateString(), 'name' => $document->revoker?->name ?? __('Barangay Captain')]) }}</p>
                                <p class="mt-2 leading-6">{{ $document->revoke_reason }}</p>
                            @elseif ($status === \App\Enums\DocumentStatus::Superseded)
                                <p class="font-semibold text-slate-950 dark:text-white">{{ __('Superseded :date by :name', ['date' => $document->superseded_at?->toFormattedDateString(), 'name' => $document->superseder?->name ?? __('Barangay Captain')]) }}</p>
                                <p class="mt-2 leading-6">{{ $document->supersede_reason }}</p>

                                @if ($document->supersededByDocumentRecord)
                                    <a class="mt-3 block break-all font-semibold text-emerald-800 hover:underline dark:text-emerald-300" href="{{ $document->supersededByDocumentRecord->verificationUrl() }}" target="_blank">
                                        {{ __('Replacement: :number', ['number' => $document->supersededByDocumentRecord->document_number]) }}
                                    </a>
                                @elseif ($document->supersede_reference)
                                    <p class="mt-3 font-semibold text-slate-950 dark:text-white">{{ __('Replacement: :reference', ['reference' => $document->supersede_reference]) }}</p>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if ($publicPdfAvailable)
                        <div class="mt-8 grid gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:grid-cols-2 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                            <flux:button variant="primary" icon="document-text" :href="route('verification.document', $document->verification_token)" target="_blank">{{ __('View Official PDF') }}</flux:button>
                            <flux:button variant="outline" icon="document-arrow-down" :href="route('verification.document', ['token' => $document->verification_token, 'download' => 1])">{{ __('Download Official PDF') }}</flux:button>
                        </div>
                    @endif

                    @unless ($hasApprovedMetadata)
                        <p class="mt-7 text-center text-xs leading-5 text-slate-400">{{ __('Verification token found, but this record is not yet an approved public document.') }}</p>
                    @endunless
                </div>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
