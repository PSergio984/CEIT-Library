<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

{{-- Modernized Update Password Form with DaisyUI --}}
<section class="max-w-xl mx-auto">
    <div class="card bg-base-100 shadow-2xl">
        <div class="card-body p-6 sm:p-10">
            {{-- Header --}}
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="bg-primary/10 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-base-content">
                            {{ __('Update Password') }}
                        </h2>
                    </div>
                </div>
                <p class="text-sm text-base-content/70 ml-1">
                    {{ __('Ensure your account is using a long, random password to stay secure.') }}
                </p>
            </div>

            {{-- Form --}}
            <form wire:submit="updatePassword" class="space-y-5" x-data="{
                showCurrentPassword: false,
                showNewPassword: false,
                showConfirmPassword: false
            }">
                {{-- Current Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_current_password">
                        <span class="label-text font-medium">Current Password</span>
                    </label>
                    <div class="relative">
                        <input 
                            wire:model="current_password" 
                            id="update_password_current_password" 
                            name="current_password"
                            :type="showCurrentPassword ? 'text' : 'password'"
                            placeholder="Enter your current password"
                            class="input input-bordered focus:input-primary w-full transition-all pr-12"
                            autocomplete="current-password"
                        />
                        <button 
                            type="button" 
                            @click="showCurrentPassword = !showCurrentPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/60 hover:text-base-content transition-colors"
                            tabindex="-1"
                        >
                            <svg x-show="!showCurrentPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showCurrentPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>

                {{-- New Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_password">
                        <span class="label-text font-medium">New Password</span>
                    </label>
                    <div class="relative">
                        <input 
                            wire:model="password" 
                            id="update_password_password" 
                            name="password" 
                            :type="showNewPassword ? 'text' : 'password'"
                            placeholder="Enter your new password"
                            class="input input-bordered focus:input-primary w-full transition-all pr-12"
                            autocomplete="new-password"
                        />
                        <button 
                            type="button" 
                            @click="showNewPassword = !showNewPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/60 hover:text-base-content transition-colors"
                            tabindex="-1"
                        >
                            <svg x-show="!showNewPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showNewPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    {{--
                        Password requirements are enforced by Password::defaults() in the validation rules in this component.
                        If you change the password policy, update this message to match the new rules.
                        For dynamic guidance, pass a computed requirements string from the component.
                    --}}
                    <label class="label">
                        <span class="label-text-alt text-base-content/60">
                            Use at least 8 characters with a mix of letters, numbers & symbols
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                {{-- Confirm Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_password_confirmation">
                        <span class="label-text font-medium">Confirm New Password</span>
                    </label>
                    <div class="relative">
                        <input 
                            wire:model="password_confirmation" 
                            id="update_password_password_confirmation"
                            name="password_confirmation" 
                            :type="showConfirmPassword ? 'text' : 'password'"
                            placeholder="Re-enter your new password"
                            class="input input-bordered focus:input-primary w-full transition-all pr-12"
                            autocomplete="new-password"
                        />
                        <button 
                            type="button" 
                            @click="showConfirmPassword = !showConfirmPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/60 hover:text-base-content transition-colors"
                            tabindex="-1"
                        >
                            <svg x-show="!showConfirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showConfirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                {{-- Submit Button & Success Message --}}
                <div class="flex flex-col items-center gap-4 pt-4">
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updatePassword"
                        class="btn btn-primary btn-block gap-2 shadow-lg">
                        <svg wire:loading.remove wire:target="updatePassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span wire:loading.remove wire:target="updatePassword">{{ __('Update Password') }}</span>
                        <span wire:loading wire:target="updatePassword" class="loading loading-spinner loading-sm"></span>
                        <span wire:loading wire:target="updatePassword">Updating...</span>
                    </button>
                    
                    {{-- Success Message --}}
                    <x-action-message class="text-success font-semibold flex items-center gap-2" on="password-updated">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('Password updated successfully!') }}
                    </x-action-message>
                </div>
            </form>

            {{-- Security Tips --}}
            <div class="alert alert-info mt-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm">
                    <p class="font-semibold">Security Tips:</p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                        <li>Don't reuse passwords from other sites</li>
                        <li>Consider using a password manager</li>
                        <li>Change your password regularly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
