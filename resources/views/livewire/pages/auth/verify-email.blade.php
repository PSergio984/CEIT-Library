<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Verify Email - CEIT Library')]
class extends Component
{
    /**
     * Send an email verification notification to the user.
     * Allow 3 attempts per 60 seconds
     */
    #[\Livewire\Attributes\Throttle(3, 60)]
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

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
        @if (session('verification-sent'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ session('verification-sent') }}</p>
                    </div>
                </div>
            </div>
        @endif
        
        <div class="mb-4 text-sm sm:text-base md:text-lg text-gray-700">
            {{ __('Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>
        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm sm:text-base text-green-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif
        <div class="mt-4 flex flex-col items-center gap-4">
            <x-primary-button wire:click="sendVerification"
                              class="w-3/4 !bg-[#273F4F] !text-white !border-none !hover:bg-[#1d2c38] flex items-center justify-center px-4 py-3 normal-case">
                <span
                    class="text-center text-sm sm:text-base leading-tight break-words">{{ __('Resend verification email') }}</span>
            </x-primary-button>
            <button wire:click="logout" type="submit"
                    class="underline text-sm sm:text-base text-gray-700 hover:text-gray-900 rounded-md font-bold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#273F4F]">
                {{ __('Log Out') }}
            </button>
        </div>
    </div>
</div>
