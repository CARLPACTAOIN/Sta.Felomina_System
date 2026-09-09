<x-layouts::app :title="__('Pending Approvals')">
    <div class="app-content">
        <x-page-header
            :title="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Pending Approvals') : __('Approval Status')"
            :description="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Review submitted metadata and final PDFs before publication.') : __('Track documents submitted for Captain review and returned revision comments.')"
        />

        <x-foundation-notice>{{ __('Approval actions remain unavailable until document status transitions, PDF versioning, notifications, and audit events are implemented together.') }}</x-foundation-notice>

        <x-panel :title="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Review queue') : __('Submitted records')" :description="__('Records are ordered by submission time when the workflow is connected.')" :padding="false">
            <x-empty-state
                :title="__('Nothing awaiting review')"
                :description="auth()->user()->role === \App\Enums\UserRole::Captain ? __('Submitted documents will appear here with metadata, PDF preview, and approve or return actions.') : __('Your submitted and Needs Revision records will appear here with the Captain’s comments.')"
                icon="inbox-arrow-down"
            />
        </x-panel>
    </div>
</x-layouts::app>
