<?php

use App\Rules\PlvEmailDomain;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Forgot Password - CEIT Library')]
class extends Component
{
    public string $email = '';

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

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            RateLimiter::hit($key, $decaySeconds);

            return;
        }

        RateLimiter::clear($key);
        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

    <!-- Main Content Card -->
    <div class="relative w-9/12 max-w-2xl mx-auto">
        <!-- Card Header with curve and logo -->
        <div class="relative z-20">
            <div class="bg-[#273F4F] h-24 rounded-t-2xl flex items-center justify-center overflow-hidden">
                <div class="absolute left-1/2 top-20 transform -translate-x-1/2 -translate-y-1/2 z-20">
                    <img src="{{ asset('images/ceit-logo.png') }}" alt="CEIT Logo"
                        class="w-20 h-20 rounded-full border-4 border-[#D9D9D9] bg-white shadow-lg">
                </div>
            </div>
        </div>
        <!-- Card Body -->
        <div class="bg-[#D9D9D9] rounded-b-2xl pt-20 pb-12 px-8 sm:px-14 shadow-2xl -mt-8 relative z-10">
            <div class="mb-4 text-base sm:text-lg md:text-xl text-gray-700">
                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </div>


            <x-mary-form wire:submit="sendPasswordResetLink">
                <!-- Email Address -->
                <div>
                    <x-text-input wire:model="email" id="email" name="email" type="email"
                                placeholder="Email"
                                class="block mt-4 w-full px-3 py-2 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                                required autofocus/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2"/>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mt-4 text-green-600 text-center text-base sm:text-lg"
                                    :status="session('status')"/>

                <div class="flex flex-col items-center mt-6">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="sendPasswordResetLink"
                                    class="w-full">
                        {{ __('Email Password Reset Link') }}
                    </x-primary-button>
                </div>
            </x-mary-form>
        </div>
    </div>
