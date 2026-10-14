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
use App\Http\Controllers\ReportPrintController;
use App\Models\DocumentRecord;
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
