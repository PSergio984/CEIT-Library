<?php

use App\Livewire\AdminDashboard;
use App\Livewire\AdminShowThesis;
use App\Livewire\AdminThesisIndex;
use App\Livewire\CreateThesis;
use App\Livewire\EditThesis;
use App\Livewire\Pages\ShowThesis;
use App\Livewire\Pages\ThesisIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// User routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/thesis', ThesisIndex::class)->name('thesis.index');
    Route::get('/thesis/{thesis}', ShowThesis::class)->name('thesis.show');
});

// Admin routes
Route::middleware(['auth', 'can:admin-access', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/thesis', AdminThesisIndex::class)->name('thesis.index');
        Route::get('/thesis/create', CreateThesis::class)->name('thesis.create');
        Route::get('/thesis/{thesis}', AdminShowThesis::class)->name('thesis.show');
        Route::get('/thesis/{thesis}/edit', EditThesis::class)->name('thesis.edit');
    });

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
