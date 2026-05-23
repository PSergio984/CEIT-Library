<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->middleware('throttle:5,1')
        ->name('register');

    Volt::route('login', 'pages.auth.login')
        ->middleware('throttle:login')
        ->name('login');

    // Rate limit: 3 attempts per minute for forgot password
    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->middleware('throttle:3,1')
        ->name('password.request');

    // Rate limit: 3 attempts per minute for reset password
    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->middleware('throttle:3,1')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    // Allow up to 6 requests per minute for the verification notice (less sensitive)
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->middleware('throttle:6,1')
        ->name('verification.notice');

    // Restrict actual verification action to 3 per minute to prevent abuse
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:3,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
