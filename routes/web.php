<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

// Test route for QR code system (only available in non-production environments)
if (config('app.env') !== 'production') {
    Route::middleware(['auth', 'verified', 'librarian.or.admin'])->group(function () {
        Route::get('/test-qr', \App\Livewire\TestQrScanner::class)->name('test-qr');
    });
}

// User routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', \App\Livewire\Pages\Student\StudentDashboard::class);
    Route::get('/academic-papers/{academicPaper}', \App\Livewire\Pages\Student\ShowAcademicPaper::class)
        ->whereNumber('academicPaper')
        ->name('academic-paper.show');
    Route::get('/academic-papers', \App\Livewire\Pages\Student\AcademicPaperIndex::class)
        ->name('academic-paper.index');
    Route::get('/rule-and-regulation', \App\Livewire\Pages\Student\RuleAndRegulationIndex::class)->name('rules-and-regulations.index');
    Route::get('/credit-score-history', \App\Livewire\Pages\Student\CreditScoreHistory::class)->name('CreditScoreHistory');
    Route::get('/transactions', \App\Livewire\Pages\Student\Transaction::class)->name('transactions');
    Route::get('/notifications', \App\Livewire\Pages\Student\StudentNotifications::class)->name('notifications');

    // QR Code download route
    Route::get('/qr-code/download/{inventoryId}', [\App\Http\Controllers\QrCodeDownloadController::class, 'download'])
        ->whereNumber('inventoryId')
        ->name('qr-code.download');
});

// Admin routes - Granular permission control
// Librarians can access some pages but not all
Route::middleware(['auth', 'verified', 'librarian.or.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Pages accessible by both Admin and Librarian
        Route::get('/dashboard', \App\Livewire\Pages\Admin\AdminDashboard::class)
            ->middleware('can:access-admin-dashboard')
            ->name('dashboard');

        Route::get('/logs', \App\Livewire\Pages\Admin\AdminBorrowTransactions::class)
            ->middleware('can:view-borrow-logs')
            ->name('borrow-logs');

        // Notifications - Accessible by both Admin and Librarian
        Route::get('/notifications', \App\Livewire\Pages\Admin\AdminNotifications::class)
            ->name('notifications');

        // Rules and Regulations - Librarians can VIEW but not EDIT
        Route::get('/rule-and-regulation', \App\Livewire\Pages\Admin\AdminRuleAndRegulationIndex::class)
            ->middleware('can:view-rules')
            ->name('rules-and-regulations.index');

        // SUPER ADMIN ONLY ROUTES

        // Academic Papers - VIEW (Librarian and Admin can view)
        Route::middleware('can:view-academic-papers')->group(function () {
            Route::get('/academic-papers', \App\Livewire\Pages\Admin\AdminAcademicPaperIndex::class)
                ->name('academic-paper.index');
            Route::get('/academic-papers/{academicPaper}', \App\Livewire\Pages\Admin\AdminShowAcademicPaper::class)
                ->whereNumber('academicPaper')
                ->name('academic-paper.show');
        });

        // Academic Papers - Create/Edit (Super Admin only)
        Route::middleware('can:manage-academic-papers')->group(function () {
            Route::get('/academic-papers/create', \App\Livewire\Pages\Admin\CreateAcademicPaper::class)->name('academic-paper.create');
            Route::get('/academic-papers/{academicPaper}/edit', \App\Livewire\Pages\Admin\EditAcademicPaper::class)->name('academic-paper.edit');
        });

        // Advisers & Deans management (Super Admin only)
        Route::get('/advisers-deans', \App\Livewire\Pages\Admin\AdminAdvisersDeans::class)
            ->middleware('can:manage-academic-papers')
            ->name('advisers-deans');

        // Attendance logs (Super Admin only)
        Route::get('/attendance', \App\Livewire\Pages\Admin\AdminAttendanceLogIndex::class)
            ->middleware('can:view-attendance-logs')
            ->name('attendance-logs');

        // Violation logs (Librarian and Admin can view)
        Route::get('/violations', \App\Livewire\Pages\Admin\AdminViolationLogIndex::class)
            ->middleware('can:view-violation-logs')
            ->name('violation-logs');

        // Librarian batch assignment (Super Admin only)
        Route::get('/librarians', \App\Livewire\Pages\Admin\AdminAssignLibrarians::class)
            ->middleware('can:manage-librarian-batches')
            ->name('librarians');

        // Role management (Super Admin only)
        Route::get('/manage-roles', \App\Livewire\Pages\Admin\AdminManageRoles::class)
            ->middleware('can:manage-user-roles')
            ->name('manage-roles');

        // // Student management (Admin only)
        // Route::get('/students', AdminUserList::class)
        //     ->middleware('can:manage-students')
        //     ->name('user-list');
    });

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

require __DIR__ . '/auth.php';
