<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-[#f4f7f5] antialiased dark:bg-slate-950">
    <a href="#documents" class="sr-only focus:not-sr-only">Skip to documents</a>
    <header class="border-b border-emerald-100 bg-white dark:border-slate-800 dark:bg-slate-900">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8" aria-label="Main navigation">
            <x-app-logo href="{{ route('home') }}" />
            <div class="flex items-center gap-5 text-sm font-semibold"><a href="#documents" class="hidden text-emerald-800 sm:block dark:text-emerald-300">Documents</a><a href="#verification" class="hidden text-emerald-800 md:block dark:text-emerald-300">Verification</a><a href="#questions" class="hidden text-emerald-800 md:block dark:text-emerald-300">Common questions</a><a href="{{ route('login') }}" class="rounded-lg border border-emerald-800 px-4 py-2.5 text-emerald-900 hover:bg-emerald-50 dark:border-emerald-400 dark:text-emerald-200 dark:hover:bg-slate-800">Staff sign in</a></div>
        </nav>
    </header>
    <main>
        <section class="relative isolate overflow-hidden bg-emerald-950 text-white">
            <img src="{{ asset('images/community-landscape.png') }}" alt="" aria-hidden="true" fetchpriority="high" class="absolute inset-0 -z-20 h-full w-full object-cover object-center" />
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-emerald-950/70 lg:bg-gradient-to-r lg:from-emerald-950/85 lg:via-emerald-950/75 lg:to-emerald-950/40"></div>
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 sm:py-24 lg:min-h-[440px] lg:grid-cols-[1fr_350px] lg:items-end">
                <div><p class="text-sm font-semibold tracking-wide text-amber-200">Barangay Sta. Felomina · Makilala, Cotabato</p><h1 class="mt-4 max-w-3xl text-4xl font-bold leading-tight tracking-tight sm:text-5xl">Official documents, within your reach.</h1><p class="mt-5 max-w-2xl text-base leading-7 text-emerald-100">Explore published barangay records and verify official documents online.</p><a href="#documents" class="mt-8 inline-flex rounded-lg bg-amber-300 px-5 py-3 font-semibold text-emerald-950 hover:bg-amber-200">Browse published documents <span aria-hidden="true" class="ml-3">↓</span></a></div>
                <div class="rounded-2xl border border-amber-300/40 bg-emerald-950/80 p-6"><h2 class="font-semibold">Have a printed document?</h2><p class="mt-2 text-sm leading-6 text-emerald-100">Scan its QR code to check its current status and open its official verification page.</p></div>
            </div>
        </section>
        <section aria-labelledby="services-heading" class="border-b border-emerald-100 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Barangay records</p>
                    <h2 id="services-heading" class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Find the information you need</h2>
                    <p class="mt-3 leading-7 text-slate-600 dark:text-slate-300">Access selected public records and check the status of a document issued by the barangay.</p>
                </div>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-6 dark:border-slate-700 dark:bg-slate-800/50">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200"><flux:icon.magnifying-glass class="size-5" aria-hidden="true" /></span>
                        <h3 class="mt-5 text-lg font-bold text-slate-950 dark:text-white">Browse public records</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Search published documents by title or number and narrow the list by document type.</p>
                        <a href="#documents" class="mt-4 inline-block text-sm font-semibold text-emerald-800 hover:underline dark:text-emerald-300">Explore the library <span aria-hidden="true">→</span></a>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800/50">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200"><flux:icon.qr-code class="size-5" aria-hidden="true" /></span>
                        <h3 class="mt-5 text-lg font-bold text-slate-950 dark:text-white">Verify a document</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Use the QR code on an official document to see its current verification status.</p>
                        <a href="#verification" class="mt-4 inline-block text-sm font-semibold text-emerald-800 hover:underline dark:text-emerald-300">How verification works <span aria-hidden="true">→</span></a>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800/50">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200"><flux:icon.document-text class="size-5" aria-hidden="true" /></span>
                        <h3 class="mt-5 text-lg font-bold text-slate-950 dark:text-white">Request a document</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">For a resident document request, contact your assigned Purok Chairperson for assistance.</p>
                        <a href="#questions" class="mt-4 inline-block text-sm font-semibold text-emerald-800 hover:underline dark:text-emerald-300">Read common questions <span aria-hidden="true">→</span></a>
                    </article>
                </div>
            </div>
        </section>
        <section id="documents" class="mx-auto max-w-7xl scroll-mt-4 px-5 py-12 sm:px-8 sm:py-16">
            <div class="max-w-2xl"><p class="text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Public library</p><h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Published documents</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-300">Search official records selected for public viewing.</p></div>
            <form method="get" action="{{ route('home') }}#documents" class="mt-8 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row dark:border-slate-700 dark:bg-slate-900">
                <label class="flex-1"><span class="sr-only">Search title or document number</span><input name="q" value="{{ request('q') }}" maxlength="200" placeholder="Search title or document number" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></label>
                <label><span class="sr-only">Document type</span><select name="type" class="w-full rounded-lg border-slate-300 sm:w-48 dark:border-slate-600 dark:bg-slate-800 dark:text-white"><option value="">All types</option>@foreach ($types as $type)<option value="{{ $type->id }}" @selected((string) request('type') === (string) $type->id)>{{ $type->name }}</option>@endforeach</select></label>
                <button class="rounded-lg bg-emerald-800 px-6 py-2.5 font-semibold text-white hover:bg-emerald-900">Search</button>
            </form>
            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    @forelse ($documents as $document)
                        <article class="border-b border-slate-100 p-5 last:border-0 dark:border-slate-800"><p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ $document->type->name }} · {{ $document->document_number }}</p><h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $document->title }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Approved {{ $document->approved_at?->toFormattedDateString() }}</p><a class="mt-3 inline-block font-semibold text-emerald-800 hover:underline dark:text-emerald-300" href="{{ route('home', array_merge(request()->only('q', 'type', 'page'), ['document' => $document->verification_token])) }}#viewer">View document →</a></article>
                    @empty
                        <div class="p-8 text-slate-600 dark:text-slate-300">{{ request()->filled('q') || request()->filled('type') ? 'No documents match your search. Try a different title, number, or type.' : 'No documents are available in the public library yet.' }}</div>
                    @endforelse
                    @if ($documents->hasPages())<div class="p-5">{{ $documents->links() }}</div>@endif
                </div>
                <div id="viewer" class="scroll-mt-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    @if ($selected)
                        <h3 class="text-xl font-bold text-slate-950 dark:text-white">{{ $selected->title }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $selected->document_number }} · {{ $selected->type->name }}</p>
                        <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300"><a href="{{ $selected->publicDocumentUrl() }}" target="_blank" rel="noopener" class="hover:underline">Open PDF</a><a href="{{ $selected->publicDocumentUrl() }}?download=1" class="hover:underline">Download PDF</a><a href="{{ $selected->verificationUrl() }}" class="hover:underline">Verify document</a></div>
                        <iframe title="{{ __('PDF preview for :title', ['title' => $selected->title]) }}" src="{{ $selected->publicDocumentUrl() }}" class="mt-5 h-[600px] w-full rounded-lg border border-slate-200 dark:border-slate-700"></iframe>
                    @else
                        <div class="flex min-h-64 flex-col items-center justify-center px-5 text-center"><h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ request()->filled('document') ? 'Document unavailable' : 'Select a document to view' }}</h3><p class="mt-2 max-w-xs text-sm leading-6 text-slate-500 dark:text-slate-400">{{ request()->filled('document') ? 'This document is no longer available in the public library.' : 'Choose a record from the list to preview its official PDF.' }}</p></div>
                    @endif
                </div>
            </div>
        </section>
        <section id="verification" class="scroll-mt-4 bg-emerald-950 text-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-2 lg:gap-20">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-emerald-300">QR verification</p>
                    <h2 class="mt-3 max-w-lg text-3xl font-bold tracking-tight sm:text-4xl">Check the status of an official document</h2>
                    <p class="mt-4 max-w-lg leading-7 text-emerald-100">The QR code connects a document to its official verification page, where you can check its current status and available details.</p>
                </div>
                <ol class="grid gap-5">
                    <li class="flex gap-4"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-800 font-bold">1</span><div><h3 class="font-semibold">Scan the QR code</h3><p class="mt-1 text-sm leading-6 text-emerald-100">Use the camera or QR scanner on your device to open the link printed on the document.</p></div></li>
                    <li class="flex gap-4"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-800 font-bold">2</span><div><h3 class="font-semibold">Check the status and details</h3><p class="mt-1 text-sm leading-6 text-emerald-100">Compare the document number and title with the information on the verification page.</p></div></li>
                    <li class="flex gap-4"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-800 font-bold">3</span><div><h3 class="font-semibold">Open the official PDF</h3><p class="mt-1 text-sm leading-6 text-emerald-100">When available, view or download the approved copy from the verification page.</p></div></li>
                </ol>
            </div>
        </section>
        <section id="questions" class="mx-auto grid max-w-7xl scroll-mt-4 gap-8 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1fr_1.4fr] lg:gap-20">
            <div><p class="text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Helpful information</p><h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Common questions</h2><p class="mt-3 max-w-sm leading-7 text-slate-600 dark:text-slate-300">A quick guide to the public library and document verification.</p></div>
            <div class="divide-y divide-slate-200 border-y border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <details class="group py-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-950 dark:text-white">Do I need an account to view public documents?<span aria-hidden="true" class="text-xl text-emerald-700 group-open:rotate-45 dark:text-emerald-300">+</span></summary><p class="mt-3 pr-8 text-sm leading-6 text-slate-600 dark:text-slate-300">No. Anyone can browse the public library and open a document's verification page.</p></details>
                <details class="group py-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-950 dark:text-white">Why can't I find a document in the library?<span aria-hidden="true" class="text-xl text-emerald-700 group-open:rotate-45 dark:text-emerald-300">+</span></summary><p class="mt-3 pr-8 text-sm leading-6 text-slate-600 dark:text-slate-300">The library contains selected published records. If your document has a QR code, scan it to open its verification page.</p></details>
                <details class="group py-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-950 dark:text-white">How do I request a resident document?<span aria-hidden="true" class="text-xl text-emerald-700 group-open:rotate-45 dark:text-emerald-300">+</span></summary><p class="mt-3 pr-8 text-sm leading-6 text-slate-600 dark:text-slate-300">Requests are handled in person through your assigned Purok Chairperson, who can assist with submitting your request.</p></details>
            </div>
        </section>
    </main>
    <footer class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div><p class="font-semibold text-slate-900 dark:text-white">Barangay Sta. Felomina</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Makilala, Cotabato · Records &amp; Verification</p></div>
            <nav aria-label="Footer navigation" class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"><a href="#documents" class="hover:underline">Documents</a><a href="#verification" class="hover:underline">Verification</a><a href="#questions" class="hover:underline">Common questions</a><a href="{{ route('login') }}" class="hover:underline">Staff sign in</a></nav>
        </div>
    </footer>
    @fluxScripts
</body>
</html>
