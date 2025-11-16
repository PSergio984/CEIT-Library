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
            <form wire:submit="updatePassword" class="space-y-5">
                {{-- Current Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_current_password">
                        <span class="label-text font-medium">Current Password</span>
                    </label>
                    <input 
                        wire:model="current_password" 
                        id="update_password_current_password" 
                        name="current_password"
                        type="password"
                        placeholder="Enter your current password"
                        class="input input-bordered focus:input-primary w-full transition-all"
                        autocomplete="current-password"
                    />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>

                {{-- New Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_password">
                        <span class="label-text font-medium">New Password</span>
                    </label>
                    <input 
                        wire:model="password" 
                        id="update_password_password" 
                        name="password" 
                        type="password"
                        placeholder="Enter your new password"
                        class="input input-bordered focus:input-primary w-full transition-all"
                        autocomplete="new-password"
                    />
                    <label class="label">
                        <span class="label-text-alt text-base-content/60">Use at least 8 characters with a mix of letters, numbers & symbols</span>
                    </label>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                {{-- Confirm Password --}}
                <div class="form-control">
                    <label class="label" for="update_password_password_confirmation">
                        <span class="label-text font-medium">Confirm New Password</span>
                    </label>
                    <input 
                        wire:model="password_confirmation" 
                        id="update_password_password_confirmation"
                        name="password_confirmation" 
                        type="password"
                        placeholder="Re-enter your new password"
                        class="input input-bordered focus:input-primary w-full transition-all"
                        autocomplete="new-password"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                {{-- Submit Button & Success Message --}}
                <div class="flex flex-col items-center gap-4 pt-4">
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updatePassword"
                        class="btn btn-primary btn-block gap-2 shadow-lg">
                        <span wire:loading.remove wire:target="updatePassword">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            {{ __('Update Password') }}
                        </span>
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
