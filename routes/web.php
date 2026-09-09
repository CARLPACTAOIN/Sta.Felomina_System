<?php

use App\Enums\UserRole;
use App\Http\Controllers\DocumentRecordController;
use App\Http\Controllers\DocumentVerificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('verify/{token}', DocumentVerificationController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('verification.show');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:'.UserRole::Captain->value.','.UserRole::Secretary->value)->group(function () {
        Route::get('documents', [DocumentRecordController::class, 'index'])->name('documents.index');
        Route::livewire('documents/create', 'pages::documents.create')->name('documents.create');
        Route::view('approvals', 'approvals.index')->name('approvals.index');
        Route::view('categorization-rules', 'categorization-rules.index')->name('categorization-rules.index');
    });

    Route::view('residents', 'residents.index')->name('residents.index');
    Route::view('households', 'households.index')->name('households.index');
    Route::view('reports', 'reports.index')->name('reports.index');
    Route::view('notifications', 'notifications.index')->name('notifications.index');

    Route::middleware('role:'.UserRole::Captain->value)->group(function () {
        Route::view('staff-accounts', 'staff-accounts.index')->name('staff-accounts.index');
        Route::view('audit-logs', 'audit-logs.index')->name('audit-logs.index');
    });
});

if (app()->environment(['local', 'testing'])) {
    Route::view('verification-preview/{state?}', 'verification.preview')
        ->whereIn('state', ['draft', 'pending', 'published', 'archived', 'revoked', 'superseded'])
        ->name('verification.preview');
}

require __DIR__.'/settings.php';
