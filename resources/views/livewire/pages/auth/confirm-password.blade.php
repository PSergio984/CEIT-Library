<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Confirm Password - CEIT Library')]
class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative w-full max-w-2xl mx-auto" x-data="{
    password: '',
    touched: false,
    error: '',
    validatePassword() {
        this.touched = true;
        if (!this.password) {
            this.error = 'Password is required.';
        } else {
            this.error = '';
        }
    },
    get isFormValid() {
        return this.password && !this.error;
    }
}">
    <!-- Card Header with curve and logo -->
    <div class="relative z-20">
        <div class="bg-[#273F4F] dark:bg-slate-950 h-24 rounded-t-2xl flex items-center justify-center overflow-hidden transition-colors duration-300">
            <div class="absolute left-1/2 top-20 transform -translate-x-1/2 -translate-y-1/2 z-20">
                <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
                     class="w-20 h-20 rounded-full border-4 border-[#D9D9D9] bg-white shadow-lg">
            </div>
        </div>
    </div>
    <!-- Card Body -->
    <div class="bg-[#D9D9D9] dark:bg-slate-900 rounded-b-2xl pt-20 pb-12 px-8 sm:px-14 shadow-2xl -mt-8 relative z-10 transition-colors duration-300">
        <h2 class="text-xl sm:text-2xl font-bold text-[#273F4F] dark:text-white text-center mb-6">Confirm Password</h2>
        <div class="mb-6 text-sm sm:text-base text-slate-700 dark:text-slate-300 text-center">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </div>

        <form wire:submit="confirmPassword" class="space-y-6">
            <!-- Password -->
            <div>
                <x-text-input wire:model="password"
                              id="password"
                              placeholder="Password"
                              class="block w-full px-4 py-3 text-base text-slate-900 dark:text-white bg-white dark:bg-slate-800 border border-gray-400 dark:border-slate-700 rounded-lg focus:border-[#273F4F] dark:focus:border-sky-400 focus:ring-[#273F4F] dark:focus:ring-sky-400 focus:ring-2 focus:outline-none placeholder-gray-500 dark:placeholder-slate-400 transition-colors duration-300"
                              type="password"
                              name="password"
                              required autocomplete="current-password"
                              x-on:input="password = $event.target.value"
                              x-on:blur="validatePassword()" />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                <template x-if="touched && error && !$wire.__instance.snapshot.memo.errors?.password">
                    <p class="text-red-600 dark:text-red-400 text-xs mt-1" x-text="error"></p>
                </template>
            </div>

            <div class="flex justify-center mt-6">
                <x-primary-button
                    class="w-full sm:w-2/3 md:w-1/2 dark:bg-sky-600 dark:hover:bg-sky-500 dark:text-white"
                    x-bind:disabled="!isFormValid"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                    {{ __('Confirm') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
