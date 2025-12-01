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
        <form wire:submit="resetPassword" class="space-y-7" x-data="{
            showPassword: false,
            showConfirmPassword: false,
            requirements: {
                length: false,
                uppercase: false,
                lowercase: false,
                number: false,
                symbol: false
            },
            evaluatePassword(value) {
                this.requirements.length = value && value.length >= 8;
                this.requirements.uppercase = /[A-Z]/.test(value || '');
                this.requirements.lowercase = /[a-z]/.test(value || '');
                this.requirements.number = /\d/.test(value || '');
                this.requirements.symbol = /[!@#$%^&*(),.?:{}|<>\[\]\\\/]/.test(value || '');
            }
        }">
            <div>
                <div class="relative">
                    <x-text-input wire:model="password" id="password" name="password" 
                                  ::type="showPassword ? 'text' : 'password'"
                                  placeholder="New Password"
                                  class="block w-full px-4 py-3 pr-12 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500 transition-all duration-200"
                                  autocomplete="new-password"
                                  x-on:input="evaluatePassword($event.target.value)"
                    />
                    <button type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700 transition-colors duration-200">
                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2"/>
                <!-- Password Requirements Checklist -->
                <div class="mt-3 space-y-1" role="status" aria-live="polite">
                    <p class="text-xs font-semibold text-gray-700 mb-1">Password must contain:</p>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.length ? 'text-green-600 scale-110' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.length ? 'text-gray-700 font-medium' : 'text-gray-500'">At least 8 characters</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.uppercase ? 'text-green-600 scale-110' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.uppercase ? 'text-gray-700 font-medium' : 'text-gray-500'">One uppercase letter</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.lowercase ? 'text-green-600 scale-110' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.lowercase ? 'text-gray-700 font-medium' : 'text-gray-500'">One lowercase letter</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.number ? 'text-green-600 scale-110' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.number ? 'text-gray-700 font-medium' : 'text-gray-500'">One number</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.symbol ? 'text-green-600 scale-110' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.symbol ? 'text-gray-700 font-medium' : 'text-gray-500'">One special character (!@#$%^&*(),.?":{}|<>)</span>
                    </div>
                </div>
            </div>
            <div>
                <div class="relative">
                    <x-text-input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation"
                                  ::type="showConfirmPassword ? 'text' : 'password'"
                                  placeholder="Confirm Password"
                                  class="block w-full px-4 py-3 pr-12 text-base text-gray-900 bg-white border border-gray-400 rounded-lg focus:border-[#273F4F] focus:ring-[#273F4F] focus:ring-2 focus:outline-none placeholder-gray-500 transition-all duration-200"
                                  autocomplete="new-password"/>
                    <button type="button" 
                            @click="showConfirmPassword = !showConfirmPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none focus:text-gray-700 transition-colors duration-200">
                        <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"/>
            </div>
            <div class="flex flex-col items-center mt-6">
                <button type="submit"
                        class="w-full bg-[#273F4F] text-white font-bold rounded-lg py-4 text-lg shadow-md hover:bg-[#1d2c38] hover:shadow-lg active:scale-[0.98] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#273F4F] focus:ring-offset-2">
                    {{ __('Reset Password') }}
                </button>
            </div>
        </form>
    </div>
</div>
