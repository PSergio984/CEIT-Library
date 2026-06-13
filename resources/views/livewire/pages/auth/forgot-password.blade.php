<?php

use App\Rules\PlvEmailDomain;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
class extends Component
{
    public string $email = '';

    public function rendering(\Illuminate\View\View $view): void
    {
        $view->title('Forgot Password - ' . config('branding.name'));
    }

    /**
     * Send a password reset link to the provided email address.
     * Throttle logic matches verify-email
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email', new PlvEmailDomain],
        ]);

        $key = 'forgot-password|'.strtolower($this->email).'|'.request()->ip();

        // Validate throttle config using shared helper
        [$maxAttempts, $decaySeconds] = \App\Livewire\Forms\LoginForm::validatedThrottleConfig(
            config('throttle.forgot_password.limit'),
            config('throttle.forgot_password.decay'),
            'forgot_password'
        );

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('email', trans_choice('passwords.throttle', $seconds, [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]));

            return;
        }

        RateLimiter::hit($key, $decaySeconds);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

    <!-- Main Content Card -->
    <div class="relative w-full max-w-2xl mx-auto" x-data="{
        email: '',
        touched: false,
        error: '',
        validateEmail() {
            this.touched = true;
            if (!this.email.trim()) {
                this.error = 'Email is required.';
            } else if (!this.email.endsWith('@plv.edu.ph')) {
                this.error = 'Email must end with @plv.edu.ph';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
                this.error = 'Please enter a valid email address.';
            } else {
                this.error = '';
            }
        },
        get isFormValid() {
            return this.email.trim() && !this.error && this.email.endsWith('@plv.edu.ph');
        }
    }">
    <!-- Clean header and logo -->
    <div class="flex flex-col items-center justify-center mb-6">
        <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
             class="w-20 h-20 rounded-full border-4 border-slate-200/50 dark:border-white/10 bg-white dark:bg-slate-800 shadow-lg mb-4">
        <h2 class="text-xl sm:text-2xl font-extrabold text-[#0046ad] dark:text-sky-400 text-center tracking-tight">Forgot password?</h2>
    </div>

    <!-- Card Body Content -->
    <div class="w-full px-2 sm:px-6">
        <div class="mb-6 text-sm sm:text-base text-slate-600 dark:text-slate-400 text-center">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>


            <x-mary-form wire:submit="sendPasswordResetLink">
                <!-- Email Address -->
                <div class="mb-4">
                    <x-text-input wire:model="email" id="email" name="email" type="email"
                                placeholder="Email"
                                class="block w-full px-4 py-3 text-base text-slate-900 dark:text-white bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg focus:border-[#0046ad] dark:focus:border-sky-400 focus:ring-[#0046ad] dark:focus:ring-sky-400 focus:ring-2 focus:outline-none placeholder-gray-500 dark:placeholder-slate-400 transition-colors duration-300"
                                required autofocus
                                x-on:input="email = $event.target.value"
                                x-on:blur="validateEmail()"/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2"/>
                    <template x-if="touched && error && !$wire.__instance.snapshot.memo.errors?.email">
                        <p class="text-red-600 dark:text-red-400 text-xs mt-2" x-text="error"></p>
                    </template>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mt-4 text-green-600 dark:text-green-400 text-center text-base font-medium"
                                    :status="session('status')"/>

                <div class="flex justify-center mt-6">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="sendPasswordResetLink"
                                    class="w-full sm:w-2/3 md:w-1/2 bg-[#0046ad] hover:bg-[#003da0] text-white border-none rounded-xl py-3 dark:bg-sky-600 dark:hover:bg-sky-500"
                                    x-bind:disabled="!isFormValid"
                                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                        {{ __('Email Password Reset Link') }}
                    </x-primary-button>
                </div>
            </x-mary-form>
        </div>
    </div>
