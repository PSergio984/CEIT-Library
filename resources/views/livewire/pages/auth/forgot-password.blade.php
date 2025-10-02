<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

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


        <form wire:submit="sendPasswordResetLink">
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
                <button type="submit"
                        class="w-full bg-[#273F4F] text-white font-bold rounded-lg py-3 text-base sm:text-lg shadow-md hover:bg-[#1d2c38] transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#273F4F] focus:ring-offset-2">
                    {{ __('Email Password Reset Link') }}
                </button>
                <div class="mt-6 text-center text-gray-700 text-base sm:text-lg">
                    {{ __('Didn\'t get the email?') }}
                    <a href="#"
                       class="font-bold underline text-[#273F4F] hover:text-[#1d2c38]">{{ __('Resend here') }}</a>
                </div>
            </div>
        </form>
    </div>
</div>
