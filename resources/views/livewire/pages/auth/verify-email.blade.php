<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
// ...existing code...
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Verify Email - CEIT Library')]

class extends Component
{
    public ?int $throttleSeconds = null;

    /**
     * Send an email verification notification to the user.
     * Throttle logic matches login form
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        $key = 'verify-email|' . Auth::id() . '|' . request()->ip();

        // Validate throttle config using shared helper
        [$maxAttempts, $decaySeconds] = \App\Livewire\Forms\LoginForm::validatedThrottleConfig(
            config('throttle.verify_email.limit'),
            config('throttle.verify_email.decay'),
            'verify_email'
        );

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->throttleSeconds = RateLimiter::availableIn($key);
            $this->addError('sendVerification', trans('auth.verification_throttle', [
                'seconds' => $this->throttleSeconds,
                'minutes' => ceil($this->throttleSeconds / 60),
            ]));
            return;
        }

        RateLimiter::hit($key, $decaySeconds);
        $this->throttleSeconds = null;
        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

    <!-- Main Content Card -->
<div class="relative w-full max-w-2xl mx-auto">
    <!-- Clean header and logo -->
    <div class="flex flex-col items-center justify-center mb-6">
        <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
             class="w-20 h-20 rounded-full border-4 border-slate-200/50 dark:border-white/10 bg-white dark:bg-slate-800 shadow-lg mb-4">
        <h2 class="text-xl sm:text-2xl font-extrabold text-[#0046ad] dark:text-sky-400 text-center tracking-tight">Verify Email</h2>
    </div>

    <!-- Card Body Content -->
    <div class="w-full px-2 sm:px-6">
        @if (session('verification-sent'))
            <div class="mb-4 p-4 bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400 dark:text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700 dark:text-green-400">{{ session('verification-sent') }}</p>
                    </div>
                </div>
            </div>
        @endif
        
        <div class="mb-4 text-sm sm:text-base md:text-lg text-slate-700 dark:text-slate-300 text-center">
            {{ __('Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>
        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm sm:text-base text-green-600 dark:text-green-400 text-center">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif
        
        @error('sendVerification')
            <div class="mb-4 font-medium text-sm sm:text-base text-red-600 dark:text-red-400 text-center" role="alert">
                {{ $message }}
            </div>
        @enderror
        
        <div class="mt-6 flex flex-col items-center gap-4">
            <x-primary-button
                wire:click="sendVerification"
                wire:loading.attr="disabled"
                wire:target="sendVerification"
                class="w-full sm:w-2/3 md:w-1/2 bg-[#0046ad] hover:bg-[#003da0] text-white border-none rounded-xl py-3 dark:bg-sky-600 dark:hover:bg-sky-500 flex items-center justify-center normal-case">
                {{ __('Resend verification email') }}
            </x-primary-button>
            <button wire:click="logout" type="button"
                    class="text-sm text-[#0046ad] dark:text-sky-400 hover:underline font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0046ad]">
                {{ __('Log Out') }}
            </button>
        </div>
    </div>
</div>
