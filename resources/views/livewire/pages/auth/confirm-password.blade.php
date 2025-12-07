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

<div x-data="{
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
    <div class="mb-4 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form wire:submit="confirmPassword">
        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password"
                          id="password"
                          class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password"
                          x-on:input="password = $event.target.value"
                          x-on:blur="validatePassword()" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
            <template x-if="touched && error && !$wire.__instance.snapshot.memo.errors?.password">
                <p class="text-red-500 text-xs mt-1" x-text="error"></p>
            </template>
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button
                x-bind:disabled="!isFormValid"
                x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</div>
