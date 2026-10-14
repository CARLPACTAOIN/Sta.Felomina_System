<x-layouts::app :title="__('Notifications')">
    <div class="app-content max-w-5xl space-y-6">
        <x-page-header :title="__('Notifications')" :description="__('In-app updates for document submissions, approvals, and revision requests.')">
            @if ($unreadCount > 0)
                <x-slot:actions>
                    <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                        @csrf
                        <flux:button type="submit" variant="outline" icon="check-circle">
                            {{ __('Mark all as read') }}
                        </flux:button>
                    </form>
                </x-slot:actions>
            @endif
        </x-page-header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3 dark:border-slate-800">
            <a
                href="{{ route('notifications.index', ['filter' => 'all']) }}"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    'bg-slate-900 text-white dark:bg-white dark:text-slate-900' => $currentFilter === 'all',
                    'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $currentFilter !== 'all',
                ])
            >
                {{ __('All') }}
                <span class="rounded-full bg-slate-200/60 px-1.5 py-0.2 text-[10px] dark:bg-slate-700/60">{{ $totalCount }}</span>
            </a>

            <a
                href="{{ route('notifications.index', ['filter' => 'unread']) }}"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    'bg-slate-900 text-white dark:bg-white dark:text-slate-900' => $currentFilter === 'unread',
                    'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $currentFilter !== 'unread',
                ])
            >
                {{ __('Unread') }}
                <span class="rounded-full bg-emerald-100 px-1.5 py-0.2 text-[10px] text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ $unreadCount }}</span>
            </a>

            <a
                href="{{ route('notifications.index', ['filter' => 'read']) }}"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    'bg-slate-900 text-white dark:bg-white dark:text-slate-900' => $currentFilter === 'read',
                    'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' => $currentFilter !== 'read',
                ])
            >
                {{ __('Read') }}
                <span class="rounded-full bg-slate-200/60 px-1.5 py-0.2 text-[10px] dark:bg-slate-700/60">{{ $readCount }}</span>
            </a>
        </div>

        <x-panel :padding="false">
            @if ($notifications->isEmpty())
                <x-empty-state
                    :title="$currentFilter === 'unread' ? __('No unread notifications') : ($currentFilter === 'read' ? __('No read notifications') : __('You’re all caught up'))"
                    :description="$currentFilter === 'unread' ? __('All caught up! New document review or revision alerts will show here.') : __('Relevant workflow notifications will appear here after document approval events.')"
                    icon="bell"
                />
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($notifications as $notification)
                        @php
                            $isUnread = $notification->unread();
                            $message = (string) data_get($notification->data, 'message', __('Workflow notification'));
                            $docNumber = data_get($notification->data, 'document_number');
                            $docTitle = data_get($notification->data, 'title');
                            $lowerMessage = strtolower($message);
                            $isApprovalWaiting = str_contains($lowerMessage, 'awaiting approval') || str_contains($lowerMessage, 'submitted');
                            $isReturned = str_contains($lowerMessage, 'returned') || str_contains($lowerMessage, 'revision');
                            $isApproved = str_contains($lowerMessage, 'approved') || str_contains($lowerMessage, 'published');
                        @endphp

                        <div
                            @class([
                                'group flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between transition',
                                'bg-emerald-50/40 border-l-4 border-l-emerald-600 dark:bg-emerald-950/20 dark:border-l-emerald-500' => $isUnread,
                                'hover:bg-slate-50/80 dark:hover:bg-slate-800/40' => ! $isUnread,
                            ])
                            wire:key="notification-item-{{ $notification->id }}"
                        >
                            <a
                                href="{{ route('notifications.open', $notification->id) }}"
                                class="flex min-w-0 flex-1 items-start gap-3.5 focus:outline-hidden"
                            >
                                <div class="mt-0.5 shrink-0">
                                    @if ($isApprovalWaiting)
                                        <span class="flex size-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                                            <flux:icon.inbox-arrow-down class="size-4.5" />
                                        </span>
                                    @elseif ($isReturned)
                                        <span class="flex size-9 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300">
                                            <flux:icon.arrow-uturn-left class="size-4.5" />
                                        </span>
                                    @elseif ($isApproved)
                                        <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            <flux:icon.check-circle class="size-4.5" />
                                        </span>
                                    @else
                                        <span class="flex size-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            <flux:icon.bell class="size-4.5" />
                                        </span>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900 group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-400">
                                            {{ $message }}
                                        </p>
                                        @if ($isUnread)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                {{ __('Unread') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($docNumber || $docTitle)
                                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $docNumber }}</span>
                                            @if ($docTitle)
                                                <span>· {{ $docTitle }}</span>
                                            @endif
                                        </p>
                                    @endif

                                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                            </a>

                            <div class="flex shrink-0 items-center gap-2 sm:self-center">
                                @if ($isUnread)
                                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button type="submit" variant="ghost" size="sm" icon="check">
                                            {{ __('Mark read') }}
                                        </flux:button>
                                    </form>
                                @else
                                    <form action="{{ route('notifications.unread', $notification->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <flux:button type="submit" variant="ghost" size="sm" icon="eye-slash">
                                            {{ __('Mark unread') }}
                                        </flux:button>
                                    </form>
                                @endif

                                <flux:button variant="subtle" size="sm" :href="route('notifications.open', $notification->id)" icon:trailing="arrow-up-right">
                                    {{ __('Open') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 p-4 dark:border-slate-700">
                    {{ $notifications->links() }}
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts::app>
