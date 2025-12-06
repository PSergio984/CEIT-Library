<?php

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Livewire\Pages\Admin\AdminAdvisersDeans;
use App\Livewire\Pages\Admin\AdminAssignLibrarians;
use App\Livewire\Pages\Admin\AdminAttendanceLogIndex;
use App\Livewire\Pages\Admin\AdminBorrowTransactions;
use App\Livewire\Pages\Admin\AdminDashboard;
use App\Livewire\Pages\Admin\AdminNotifications;
use App\Livewire\Pages\Admin\AdminManageRoles;
use App\Livewire\Pages\Admin\AdminRuleAndRegulationIndex;
use App\Livewire\Pages\Admin\AdminShowAcademicPaper;
use App\Livewire\Pages\Admin\AdminUserList;
use App\Livewire\Pages\Admin\AdminViolationLogIndex;
use App\Livewire\Pages\Admin\CreateAcademicPaper;
use App\Livewire\Pages\Admin\EditAcademicPaper;
use App\Livewire\Pages\Student\AcademicPaperIndex;
use App\Livewire\Pages\Student\CreditScoreHistory;
use App\Livewire\Pages\Student\RuleAndRegulationIndex;
use App\Livewire\Pages\Student\ShowAcademicPaper;
use App\Livewire\Pages\Student\StudentDashboard;
use App\Livewire\Pages\Student\StudentNotifications;
use App\Livewire\Pages\Student\Transaction;
use App\Livewire\TestQrScanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Test route for QR code system (only available in non-production environments)
if (config('app.env') !== 'production') {
    Route::middleware(['auth', 'verified', 'librarian.or.admin'])->group(function () {
        Route::get('/test-qr', TestQrScanner::class)->name('test-qr');
    });
}

// User routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', StudentDashboard::class)->name('student.dashboard');
    Route::get('/academic-papers/{academicPaper}', ShowAcademicPaper::class)
        ->whereNumber('academicPaper')
        ->name('academic-paper.show');
    Route::get('/academic-papers', AcademicPaperIndex::class)
        ->name('academic-paper.index');
    Route::get('/rule-and-regulation', RuleAndRegulationIndex::class)->name('rules-and-regulations.index');
    Route::get('/credit-score-history', CreditScoreHistory::class)->name('CreditScoreHistory');
    Route::get('/transactions', Transaction::class)->name('transactions');
    Route::get('/notifications', StudentNotifications::class)->name('notifications');

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
        Route::get('/dashboard', AdminDashboard::class)
            ->middleware('can:access-admin-dashboard')
            ->name('dashboard');

        Route::get('/logs', AdminBorrowTransactions::class)
            ->middleware('can:view-borrow-logs')
            ->name('borrow-logs');

        // Notifications - Accessible by both Admin and Librarian
        Route::get('/notifications', AdminNotifications::class)
            ->name('notifications');

        // Rules and Regulations - Librarians can VIEW but not EDIT
        Route::get('/rule-and-regulation', AdminRuleAndRegulationIndex::class)
            ->middleware('can:view-rules')
            ->name('rules-and-regulations.index');

        // SUPER ADMIN ONLY ROUTES

        // Academic Papers - VIEW (Librarian and Admin can view)
        Route::middleware('can:view-academic-papers')->group(function () {
            Route::get('/academic-papers', AdminAcademicPaperIndex::class)
                ->name('academic-paper.index');
            Route::get('/academic-papers/{academicPaper}', AdminShowAcademicPaper::class)
                ->whereNumber('academicPaper')
                ->name('academic-paper.show');
        });

        // Academic Papers - Create/Edit (Super Admin only)
        Route::middleware('can:manage-academic-papers')->group(function () {
            Route::get('/academic-papers/create', CreateAcademicPaper::class)->name('academic-paper.create');
            Route::get('/academic-papers/{academicPaper}/edit', EditAcademicPaper::class)->name('academic-paper.edit');
        });

        // Advisers & Deans management (Super Admin only)
        Route::get('/advisers-deans', AdminAdvisersDeans::class)
            ->middleware('can:manage-academic-papers')
            ->name('advisers-deans');

        // Attendance logs (Super Admin only)
        Route::get('/attendance', AdminAttendanceLogIndex::class)
            ->middleware('can:view-attendance-logs')
            ->name('attendance-logs');

        // Violation logs (Librarian and Admin can view)
        Route::get('/violations', AdminViolationLogIndex::class)
            ->middleware('can:view-violation-logs')
            ->name('violation-logs');

        // Librarian batch assignment (Super Admin only)
        Route::get('/librarians', AdminAssignLibrarians::class)
            ->middleware('can:manage-librarian-batches')
            ->name('librarians');

        // Role management (Super Admin only)
        Route::get('/manage-roles', AdminManageRoles::class)
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
