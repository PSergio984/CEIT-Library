<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative w-full max-w-2xl mx-auto">
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
        <!-- Title -->
        <h2 class="text-lg sm:text-xl md:text-2xl font-semibold text-[#273F4F] text-center mb-4 sm:mb-6 md:mb-8">Login
            your account</h2>
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <x-mary-form wire:submit="login">
            <x-mary-errors title="Oops!" description="Please, fix them." icon="o-face-frown"/>


            <!-- Email Address -->
            <div class="mb-4">
                <x-mary-input
                    wire:model="form.email"
                    placeholder="Email"
                    icon="o-envelope"
                    clearable
                    type="email"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    required
                    autofocus
                    autocomplete="username"
                    error-field="form.email"/>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <x-mary-password
                    wire:model="form.password"
                    placeholder="Password"
                    required
                    autocomplete="current-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="form.password"/>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between mb-6">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                           name="remember">
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm text-[#273F4F] hover:text-[#1e2f3a] underline font-medium"
                       href="{{ route('password.request') }}" wire:navigate>
                        Forgot your password?
                    </a>
                @endif
            </div>

            <!-- Login Button -->
            <div class="mb-4">
                <x-mary-button
                    label="Login"
                    class="bg-[#273F4F] hover:bg-[#1e2f3a] text-white px-6 py-3 text-sm sm:text-base rounded-lg w-1/2 font-semibold"
                    type="submit"
                />
            </div>

            <!-- Register Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-[#273F4F] hover:text-[#1e2f3a] underline font-medium"
                       wire:navigate>
                        Register
                    </a>
                </p>
            </div>
        </x-mary-form>
    </div>
</div>
