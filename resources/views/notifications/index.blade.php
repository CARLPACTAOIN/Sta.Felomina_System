<x-layouts::app :title="__('Notifications')">
    <div class="app-content max-w-5xl">
        <x-page-header :title="__('Notifications')" :description="__('In-app updates for document submissions, approvals, and revision requests.')" />

        <x-panel :padding="false">
            @if ($notifications->isEmpty())
                <x-empty-state :title="__('You’re all caught up')" :description="__('Relevant workflow notifications will appear here after document approval events.')" icon="bell" />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($notifications as $notification)
                        @php
                            $url = data_get($notification->data, 'url');
                        @endphp

                        <a
                            href="{{ is_string($url) ? $url : '#' }}"
                            @class([
                                'block p-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                'bg-emerald-50/60 dark:bg-emerald-950/20' => $notification->unread(),
                            ])
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ data_get($notification->data, 'message', __('Workflow notification')) }}</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ data_get($notification->data, 'document_number') }} · {{ data_get($notification->data, 'title') }}</p>
                                </div>
                                <p class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $notification->created_at?->diffForHumans() }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                    {{ $notifications->links() }}
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts::app>
