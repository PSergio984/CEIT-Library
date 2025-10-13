<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Reset Password - CEIT Library')]
class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
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
        <h2 class="text-2xl font-bold text-[#273F4F] text-center mb-8">Reset your password</h2>
        <form wire:submit="resetPassword" class="space-y-7">
            <div>
                <x-text-input wire:model="password" id="password" name="password" type="password"
                              placeholder="New Password"
                              class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                              autocomplete="new-password"/>
                <x-input-error :messages="$errors->get('password')" class="mt-2"/>
            </div>
            <div>
                <x-text-input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation"
                              type="password"
                              placeholder="Confirm Password"
                              class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                              autocomplete="new-password"/>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"/>
            </div>
            <div class="flex flex-col items-center mt-6">
                <button type="submit"
                        class="w-full bg-[#273F4F] text-white font-bold rounded-lg py-4 text-lg shadow-md hover:bg-[#1d2c38] transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#273F4F] focus:ring-offset-2">
                    {{ __('Reset Password') }}
                </button>
            </div>
        </form>
    </div>
</div>
