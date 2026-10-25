<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDraftPdfController;
use App\Http\Controllers\DocumentPublishedPdfController;
use App\Http\Controllers\DocumentQrCodeController;
use App\Http\Controllers\DocumentRecordController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurokResolutionFileController;
use App\Http\Controllers\ReportPrintController;
use App\Models\DocumentRecord;
use App\Models\ResidentDocumentRequest;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('verify/{token}', DocumentVerificationController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('verification.show');

Route::get('verify/{token}/document', DocumentPublishedPdfController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('verification.document');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::livewire('documents/create', 'pages::documents.create')
        ->middleware('can:create,'.DocumentRecord::class)
        ->name('documents.create');

    Route::middleware('can:viewAny,'.DocumentRecord::class)->group(function () {
        Route::get('documents', [DocumentRecordController::class, 'index'])->name('documents.index');
        Route::livewire('documents/{documentRecord}', 'pages::documents.show')->name('documents.show');
        Route::livewire('approvals', 'pages::approvals.index')
            ->middleware('can:review,'.DocumentRecord::class)
            ->name('approvals.index');
    });
    Route::get('documents/{documentRecord}/draft-pdf', DocumentDraftPdfController::class)
        ->middleware('can:downloadDraftPdf,documentRecord')
        ->name('documents.draft-pdf');
    Route::get('documents/{documentRecord}/qr-code', DocumentQrCodeController::class)
        ->middleware('can:downloadQrCode,documentRecord')
        ->name('documents.qr-code');
    Route::livewire('categorization-rules', 'pages::categorization-rules.index')
        ->middleware('can:'.StaffPermission::ManageCategorizationRules->value)
        ->name('categorization-rules.index');

    Route::livewire('residents', 'pages::residents.index')
        ->middleware('permission:'.StaffPermission::ViewResidentsBarangay->value.'|'.StaffPermission::ViewResidentsAssigned->value)
        ->name('residents.index');
    Route::livewire('households', 'pages::households.index')
        ->middleware('permission:'.StaffPermission::ViewHouseholdsBarangay->value.'|'.StaffPermission::ViewHouseholdsAssigned->value)
        ->name('households.index');
    Route::livewire('reports', 'pages::reports.index')
        ->middleware('can:'.StaffPermission::ViewReports->value)
        ->name('reports.index');
    Route::get('reports/print', [ReportPrintController::class, 'show'])
        ->middleware('can:'.StaffPermission::ViewReports->value)
        ->name('reports.print');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.unread');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    Route::livewire('staff-accounts', 'pages::staff-accounts.index')
        ->middleware('can:'.StaffPermission::ManageStaffAccounts->value)
        ->name('staff-accounts.index');
    Route::livewire('puroks', 'pages::puroks.index')
        ->middleware('can:'.StaffPermission::ManagePuroks->value)
        ->name('puroks.index');
    Route::get('puroks/{purok}/resolution', PurokResolutionFileController::class)
        ->middleware('can:'.StaffPermission::ManagePuroks->value)
        ->name('puroks.resolution.download');
    Route::get('puroks/{purok}/resolutions/{purokResolutionFile}', PurokResolutionFileController::class)
        ->middleware('can:'.StaffPermission::ManagePuroks->value)
        ->name('puroks.resolution-files.download');
    Route::livewire('resident-document-requests', 'pages::resident-document-requests.index')
        ->middleware('can:viewAny,'.ResidentDocumentRequest::class)
        ->name('resident-document-requests.index');
    Route::livewire('resident-document-requests/create', 'pages::resident-document-requests.create')
        ->middleware('can:create,'.ResidentDocumentRequest::class)
        ->name('resident-document-requests.create');
    Route::livewire('resident-document-requests/{residentDocumentRequest}', 'pages::resident-document-requests.show')
        ->name('resident-document-requests.show');
    Route::livewire('resident-document-requests/{residentDocumentRequest}/documents/create', 'pages::documents.create')
        ->name('resident-document-requests.documents.create');
    Route::livewire('requestable-document-types', 'pages::requestable-document-types.index')
        ->middleware('can:'.StaffPermission::ConfigureRequestableDocumentTypes->value)
        ->name('requestable-document-types.index');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:'.StaffPermission::ViewAuditLogs->value)
        ->name('audit-logs.index');
    Route::get('audit-logs/print', [AuditLogController::class, 'print'])
        ->middleware('can:'.StaffPermission::ViewAuditLogs->value)
        ->name('audit-logs.print');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('can:'.StaffPermission::ViewAuditLogs->value)
        ->name('audit-logs.export');
});

if (app()->environment(['local', 'testing'])) {
    Route::view('verification-preview/{state?}', 'verification.preview')
        ->whereIn('state', ['draft', 'pending', 'published', 'archived', 'revoked', 'superseded'])
        ->name('verification.preview');
}

require __DIR__.'/settings.php';
