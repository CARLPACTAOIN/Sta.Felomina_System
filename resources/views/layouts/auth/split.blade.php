<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f4f7f5] antialiased dark:bg-slate-950">
        <main class="grid min-h-svh lg:grid-cols-[1.08fr_0.92fr]">
            <section class="relative hidden overflow-hidden bg-barangay-950 px-12 py-10 text-white lg:flex lg:flex-col xl:px-16">
                <div class="absolute -bottom-52 -left-52 size-[34rem] rounded-full border border-white/10"></div>
                <div class="absolute -right-40 -top-32 size-96 rounded-full border border-white/10"></div>

                <x-app-logo :sidebar="true" class="relative z-10" href="{{ route('home') }}" />

                <div class="relative z-10 my-auto max-w-xl">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-amber-300">Barangay Sta. Felomina</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight tracking-[-0.035em] xl:text-5xl">Official records, organized and verifiable.</h1>
                    <p class="mt-5 max-w-lg text-sm leading-7 text-emerald-100/75">A secure workspace for document archiving, approval, public QR verification, and resident profiling.</p>
                    <div class="mt-8 flex flex-wrap gap-2 text-xs text-emerald-50/80">
                        <span class="rounded-lg border border-white/10 bg-white/[0.06] px-3 py-2">Permanent QR identity</span>
                        <span class="rounded-lg border border-white/10 bg-white/[0.06] px-3 py-2">Controlled approval</span>
                        <span class="rounded-lg border border-white/10 bg-white/[0.06] px-3 py-2">Purok-scoped records</span>
                    </div>
                </div>

                <p class="relative z-10 text-xs text-emerald-100/55">Authorized barangay staff access only</p>
            </section>

            <section class="flex min-h-svh items-center justify-center px-5 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <x-app-logo class="mb-10 lg:hidden" href="{{ route('home') }}" />
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-[0_20px_55px_rgba(15,61,44,0.08)] sm:p-8 dark:border-slate-700 dark:bg-slate-900">
                        {{ $slot }}
                    </div>
                    <p class="mt-6 text-center text-xs leading-5 text-slate-500 dark:text-slate-400">Public registration is not available. Staff accounts are issued internally by the Barangay Captain.</p>
                </div>
            </section>
        </main>

        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
