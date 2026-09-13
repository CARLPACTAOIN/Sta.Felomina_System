<?php

use App\Enums\UserRole;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDraftPdfController;
use App\Http\Controllers\DocumentPublishedPdfController;
use App\Http\Controllers\DocumentQrCodeController;
use App\Http\Controllers\DocumentRecordController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportPrintController;
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

    Route::middleware('role:'.UserRole::Captain->value.','.UserRole::Secretary->value)->group(function () {
        Route::get('documents', [DocumentRecordController::class, 'index'])->name('documents.index');
        Route::livewire('documents/create', 'pages::documents.create')->name('documents.create');
        Route::livewire('documents/{documentRecord}', 'pages::documents.show')->name('documents.show');
        Route::get('documents/{documentRecord}/draft-pdf', DocumentDraftPdfController::class)->name('documents.draft-pdf');
        Route::get('documents/{documentRecord}/qr-code', DocumentQrCodeController::class)->name('documents.qr-code');
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::livewire('categorization-rules', 'pages::categorization-rules.index')->name('categorization-rules.index');
    });

    Route::livewire('residents', 'pages::residents.index')->name('residents.index');
    Route::livewire('households', 'pages::households.index')->name('households.index');
    Route::livewire('reports', 'pages::reports.index')->name('reports.index');
    Route::get('reports/print', [ReportPrintController::class, 'show'])->name('reports.print');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::middleware('role:'.UserRole::Captain->value)->group(function () {
        Route::livewire('staff-accounts', 'pages::staff-accounts.index')->name('staff-accounts.index');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});

if (app()->environment(['local', 'testing'])) {
    Route::view('verification-preview/{state?}', 'verification.preview')
        ->whereIn('state', ['draft', 'pending', 'published', 'archived', 'revoked', 'superseded'])
        ->name('verification.preview');
}

require __DIR__.'/settings.php';
