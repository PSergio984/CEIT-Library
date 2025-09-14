<?php

use App\Livewire\Pages\ThesisIndex;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Route;
Route::view('/', 'welcome');



Route::middleware(['auth','verified'])->group(function (){

    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/thesis', ThesisIndex::class)->name('thesis');

});


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
