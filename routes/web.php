<?php

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Livewire\Pages\Admin\AdminDashboard;
use App\Livewire\Pages\Admin\AdminShowAcademicPaper;
use App\Livewire\Pages\Admin\AdminRuleAndRegulationIndex;
use App\Livewire\Pages\Admin\CreateAcademicPaper;
use App\Livewire\Pages\Admin\EditAcademicPaper;
use App\Livewire\Pages\Student\AcademicPaperIndex;
use App\Livewire\Pages\Student\ShowAcademicPaper;
use App\Livewire\Pages\Student\RuleAndRegulationIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// User routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/academic-papers/{academicPaper}', ShowAcademicPaper::class)
        ->whereNumber('academicPaper')
        ->name('academic-paper.show');
    Route::get('/academic-papers', AcademicPaperIndex::class)
        ->name('academic-paper.index');
    Route::get('/academic-papers/{dept}', AcademicPaperIndex::class)
        ->where('dept', 'it|ce|ee')
        ->name('academic-paper.index.dept');
    Route::get('/rule-and-regulation',  RuleAndRegulationIndex::class)->name('rules-and-regulations.index');
});

// Admin routes
Route::middleware(['auth', 'can:Admin-access', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/academic-papers/{dept?}', AdminAcademicPaperIndex::class)
            ->where('dept', 'it|ce|ee')
            ->name('academic-paper.index');
        Route::get('/academic-papers/create', CreateAcademicPaper::class)->name('academic-paper.create');
        Route::get('/academic-papers/{academicPaper}', AdminShowAcademicPaper::class)->name('academic-paper.show');
        Route::get('/academic-papers/{academicPaper}/edit', EditAcademicPaper::class)->name('academic-paper.edit');
        Route::get('/rule-and-regulation',  AdminRuleAndRegulationIndex::class)->name('rules-and-regulations.index');
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
