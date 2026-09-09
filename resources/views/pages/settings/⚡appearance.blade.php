<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Appearance settings')] class extends Component {}; ?>

<section class="app-content max-w-6xl">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Choose how the workspace looks on this device.')">
        <x-panel :title="__('Display theme')" :description="__('Use a light workspace, a dark workspace, or follow your device setting.')">
            <div class="grid gap-3 sm:grid-cols-3" x-data>
                @foreach ([['light', 'sun', __('Light'), __('Bright, calm surfaces for daytime work.')], ['dark', 'moon', __('Dark'), __('Reduced glare in low-light environments.')], ['system', 'computer-desktop', __('System'), __('Automatically follow this device.')]] as [$value, $icon, $label, $description])
                    <button
                        type="button"
                        @click="$flux.appearance = '{{ $value }}'"
                        :aria-pressed="$flux.appearance === '{{ $value }}'"
                        :class="$flux.appearance === '{{ $value }}' ? 'border-emerald-700 bg-emerald-50 ring-2 ring-emerald-700/10 dark:border-emerald-500 dark:bg-emerald-950/30' : 'border-slate-200 bg-white hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-700'"
                        class="rounded-2xl border p-4 text-left transition"
                        wire:key="appearance-{{ $value }}"
                    >
                        <span class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"><flux:icon :name="$icon" class="size-5" /></span>
                        <span class="mt-4 block text-sm font-semibold text-slate-950 dark:text-white">{{ $label }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</span>
                    </button>
                @endforeach
            </div>
        </x-panel>
    </x-pages::settings.layout>
</section>
