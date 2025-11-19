<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
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
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/[A-Z]/', // at least one uppercase
                'regex:/[a-z]/', // at least one lowercase
                'regex:/[0-9]/', // at least one number
                'regex:/[!@#$%^&*(),.?":{}|<>]/', // at least one special character
            ],
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

        if ($status == Password::RESET_THROTTLED) {
            $seconds = config('auth.passwords.'.config('auth.defaults.passwords').'.throttle', 60);
            $this->addError('email', trans_choice(
                'Too many password reset attempts. Please try again in :seconds second.|Too many password reset attempts. Please try again in :seconds seconds.',
                $seconds,
                ['seconds' => $seconds]
            ));

            return;
        }

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
            <div x-data="{
                pwd: $wire.entangle('password').defer,
                requirements: {
                    length: false,
                    uppercase: false,
                    lowercase: false,
                    number: false,
                    symbol: false
                },
                update() {
                    this.requirements.length = this.pwd && this.pwd.length >= 8;
                    this.requirements.uppercase = /[A-Z]/.test(this.pwd || '');
                    this.requirements.lowercase = /[a-z]/.test(this.pwd || '');
                    this.requirements.number = /\d/.test(this.pwd || '');
                    this.requirements.symbol = /[!@#$%^&*(),.?\":{}|<>]/.test(this.pwd || '');
                }
            }" x-init="update()" @input="update()" @change="update()">
                <x-text-input wire:model="password" id="password" name="password" type="password"
                              placeholder="New Password"
                              class="block w-full px-4 py-3 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500"
                              autocomplete="new-password"
                              x-on:input="update()"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2"/>
                <!-- Password Requirements Checklist -->
                <div class="mt-3 space-y-1" role="status" aria-live="polite">
                    <p class="text-xs font-semibold text-gray-700 mb-1">Password must contain:</p>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" :class="requirements.length ? 'text-green-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs" :class="requirements.length ? 'text-gray-700' : 'text-gray-500'">At least 8 characters</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" :class="requirements.uppercase ? 'text-green-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs" :class="requirements.uppercase ? 'text-gray-700' : 'text-gray-500'">One uppercase letter</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" :class="requirements.lowercase ? 'text-green-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs" :class="requirements.lowercase ? 'text-gray-700' : 'text-gray-500'">One lowercase letter</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" :class="requirements.number ? 'text-green-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs" :class="requirements.number ? 'text-gray-700' : 'text-gray-500'">One number</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" :class="requirements.symbol ? 'text-green-600' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs" :class="requirements.symbol ? 'text-gray-700' : 'text-gray-500'">One special character (!@#$%^&*(),.?":{}|<>)</span>
                    </div>
                </div>
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
