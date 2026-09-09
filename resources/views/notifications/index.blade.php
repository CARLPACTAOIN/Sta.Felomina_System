<x-layouts::app :title="__('Notifications')">
    <div class="app-content max-w-5xl">
        <x-page-header :title="__('Notifications')" :description="__('In-app updates for document submissions, approvals, and revision requests.')">
            <x-slot:actions><flux:button variant="outline" disabled>{{ __('Mark all as read') }}</flux:button></x-slot:actions>
        </x-page-header>
        <x-foundation-notice>{{ __('Only in-app notifications belong in Capstone V1. SMS and email notification infrastructure are intentionally excluded.') }}</x-foundation-notice>
        <x-panel :padding="false"><x-empty-state :title="__('You’re all caught up')" :description="__('Relevant workflow notifications will appear here when document approvals are implemented.')" icon="bell" /></x-panel>
    </div>
</x-layouts::app>
