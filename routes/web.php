<?php

use App\Livewire\Pages\ThesisIndex;
use App\Livewire\Pages\ShowThesis;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');



Route::middleware(['auth','verified'])->group(function (){

    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/thesis', ThesisIndex::class)->name('thesis');
    Route::get('/thesis/{thesis}', ShowThesis::class)->name('Show Thesis');

});


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
