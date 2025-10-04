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

<section class="max-w-xl mx-auto bg-[#D9D9D9] rounded-2xl shadow-2xl p-10">
    <header class="mb-8">
        <h2 class="text-2xl font-bold text-[#273F4F] text-center">
            {{ __('Update Password') }}
        </h2>
        <p class="mt-2 text-lg text-gray-700 text-center">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="space-y-7">
        <div>
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password"
                          type="password"
                          placeholder="Current Password"
                          class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                          autocomplete="current-password"/>
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password"
                          placeholder="New Password"
                          class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                          autocomplete="new-password"/>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation"
                          name="password_confirmation" type="password"
                          placeholder="Confirm Password"
                          class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                          autocomplete="new-password"/>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col items-center gap-4 mt-6">
            <button type="submit"
                    class="w-full bg-[#273F4F] text-white font-bold rounded-lg py-4 text-lg shadow-md hover:bg-[#1d2c38] transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#273F4F] focus:ring-offset-2">
                {{ __('Save') }}
            </button>
            <x-action-message class="me-3 text-green-600 text-base font-semibold" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
