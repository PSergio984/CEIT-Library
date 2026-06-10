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
class extends Component
{
    #[Locked]
    public string $token = '';

    public function rendering(\Illuminate\View\View $view): void
    {
        $view->title('Reset Password - ' . config('branding.name'));
    }

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
<div class="relative w-full max-w-2xl mx-auto">
    <!-- Clean header and logo -->
    <div class="flex flex-col items-center justify-center mb-6">
        <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
             class="w-20 h-20 rounded-full border-4 border-slate-200/50 dark:border-white/10 bg-white dark:bg-slate-800 shadow-lg mb-4">
        <h2 class="text-xl sm:text-2xl font-extrabold text-[#0046ad] dark:text-sky-400 text-center tracking-tight">Reset your password</h2>
    </div>

    <!-- Card Body Content -->
    <div class="w-full px-2 sm:px-6">
        <form wire:submit="resetPassword" class="space-y-7" x-data="{
            showPassword: false,
            showConfirmPassword: false,
            password: '',
            password_confirmation: '',
            touched: {
                password: false,
                password_confirmation: false
            },
            errors: {
                password: '',
                password_confirmation: ''
            },
            requirements: {
                length: false,
                uppercase: false,
                lowercase: false,
                number: false,
                symbol: false
            },
            evaluatePassword(value) {
                this.password = value;
                this.requirements.length = value && value.length >= 8;
                this.requirements.uppercase = /[A-Z]/.test(value || '');
                this.requirements.lowercase = /[a-z]/.test(value || '');
                this.requirements.number = /\d/.test(value || '');
                this.requirements.symbol = /[!@#$%^&*(),.?:{}|<>\[\]\\\/]/.test(value || '');
            },
            validateField(field) {
                this.touched[field] = true;
                if (field === 'password') {
                    if (!this.password) {
                        this.errors.password = 'Password is required.';
                    } else if (!this.requirements.length || !this.requirements.uppercase || !this.requirements.lowercase || !this.requirements.number || !this.requirements.symbol) {
                        this.errors.password = 'Password does not meet all requirements.';
                    } else {
                        this.errors.password = '';
                    }
                    // Also validate confirmation if touched
                    if (this.touched.password_confirmation) {
                        this.validateField('password_confirmation');
                    }
                } else if (field === 'password_confirmation') {
                    if (!this.password_confirmation) {
                        this.errors.password_confirmation = 'Please confirm your password.';
                    } else if (this.password_confirmation !== this.password) {
                        this.errors.password_confirmation = 'Passwords do not match.';
                    } else {
                        this.errors.password_confirmation = '';
                    }
                }
            },
            get isFormValid() {
                const allReqsMet = this.requirements.length && this.requirements.uppercase && 
                                  this.requirements.lowercase && this.requirements.number && 
                                  this.requirements.symbol;
                return this.password && this.password_confirmation && 
                       allReqsMet && this.password === this.password_confirmation;
            }
        }">
            <div>
                <div class="relative">
                    <x-text-input wire:model="password" id="password" name="password" 
                                  ::type="showPassword ? 'text' : 'password'"
                                  placeholder="New Password"
                                  class="block w-full px-4 py-3 pr-12 text-base text-slate-900 dark:text-white bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg focus:border-[#0046ad] dark:focus:border-sky-400 focus:ring-[#0046ad] dark:focus:ring-sky-400 focus:ring-2 focus:outline-none placeholder-gray-500 dark:placeholder-slate-400 transition-all duration-200"
                                  autocomplete="new-password"
                                  x-on:input="evaluatePassword($event.target.value)"
                                  x-on:blur="validateField('password')"
                    />
                    <button type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white focus:outline-none focus:text-gray-700 transition-colors duration-200">
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
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1">Password must contain:</p>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.length ? 'text-green-600 dark:text-green-400 scale-110' : 'text-gray-400 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.length ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400'">At least 8 characters</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.uppercase ? 'text-green-600 dark:text-green-400 scale-110' : 'text-gray-400 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.uppercase ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400'">One uppercase letter</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.lowercase ? 'text-green-600 dark:text-green-400 scale-110' : 'text-gray-400 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.lowercase ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400'">One lowercase letter</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.number ? 'text-green-600 dark:text-green-400 scale-110' : 'text-gray-400 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.number ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400'">One number</span>
                    </div>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <svg class="w-4 h-4 transition-all duration-300" :class="requirements.symbol ? 'text-green-600 dark:text-green-400 scale-110' : 'text-gray-400 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs transition-colors duration-200" :class="requirements.symbol ? 'text-slate-800 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400'">One special character (!@#$%^&*(),.?":{}|<>)</span>
                    </div>
                </div>
            </div>
            <div>
                <div class="relative">
                    <x-text-input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation"
                                  ::type="showConfirmPassword ? 'text' : 'password'"
                                  placeholder="Confirm Password"
                                  class="block w-full px-4 py-3 pr-12 text-base text-slate-900 dark:text-white bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg focus:border-[#0046ad] dark:focus:border-sky-400 focus:ring-[#0046ad] dark:focus:ring-sky-400 focus:ring-2 focus:outline-none placeholder-gray-500 dark:placeholder-slate-400 transition-all duration-200"
                                  autocomplete="new-password"
                                  x-on:input="password_confirmation = $event.target.value"
                                  x-on:blur="validateField('password_confirmation')"/>
                    <button type="button" 
                            @click="showConfirmPassword = !showConfirmPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white focus:outline-none focus:text-gray-700 transition-colors duration-200">
                        <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 01-1.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"/>
                <template x-if="touched.password_confirmation && errors.password_confirmation && !$wire.__instance.snapshot.memo.errors?.password_confirmation">
                    <p class="text-red-600 dark:text-red-400 text-xs mt-1" x-text="errors.password_confirmation"></p>
                </template>
            </div>
            <div class="flex justify-center mt-6">
                <button type="submit"
                        class="w-full sm:w-2/3 md:w-1/2 bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl py-3 dark:bg-sky-600 dark:hover:bg-sky-500 active:scale-[0.98] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#0046ad] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        x-bind:disabled="!isFormValid"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword">
                    <span wire:loading.remove wire:target="resetPassword">{{ __('Reset Password') }}</span>
                    <span wire:loading wire:target="resetPassword" class="flex items-center justify-center">
                        <span class="loading loading-spinner loading-sm mr-2"></span>
                        Processing...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
