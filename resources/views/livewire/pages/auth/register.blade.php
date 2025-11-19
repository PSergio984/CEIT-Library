<?php

use App\Mail\Welcome;
use App\Models\User;
use App\Rules\PlvEmailDomain;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
#[Title('Register - CEIT Library')]
class extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', new PlvEmailDomain, 'email', 'max:100', 'unique:'.User::class],
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

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        // Send welcome email for testing
        Mail::to($user->email)->queue(new Welcome());

        // Temporarily log in user to access verification notice page, then redirect there
        Auth::login($user);
        session()->flash('verification-sent', 'Registration successful! Please check your email and verify your account.');
        $this->redirect(route('verification.notice'), navigate: true);
    }
}; ?>

    <div class="relative w-full max-w-2xl mx-auto" x-data="{
        passwordStrength: 0,
        passwordLabel: 'Weak',
        requirements: {
            length: false,
            number: false,
            symbol: false,
            uppercase: false,
            lowercase: false
        },
        formatName(val) {
            if (!val) return '';
            val = val.trim().replace(/\s+/g, ' ');
            return val.split(' ').map(w => w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : '').filter(Boolean).join(' ');
        },
        updateEmail() {
            const cleanFirst = (this.$wire.first_name || '').replace(/\s+/g, '').toLowerCase();
            const cleanLast = (this.$wire.last_name || '').replace(/\s+/g, '').toLowerCase();
            this.$wire.email = (cleanFirst && cleanLast) ? cleanFirst + cleanLast + '@plv.edu.ph' : '';
        },
        evaluatePassword(value) {
            this.requirements.length = value && value.length >= 8;
            this.requirements.number = /\d/.test(value || '');
            // Use single quotes for the string and escape only necessary characters in the regex
            // Avoid unescaped double quotes and problematic characters
            this.requirements.symbol = /[!@#$%^&*(),.?':{}|<>\[\]\\/]/.test(value || '');
            this.requirements.uppercase = /[A-Z]/.test(value || '');
            this.requirements.lowercase = /[a-z]/.test(value || '');
            const score = Object.values(this.requirements).filter(Boolean).length;
            this.passwordStrength = (score / 5) * 100;
            this.passwordLabel = score <= 2 ? 'Weak' : score === 3 ? 'Fair' : score === 4 ? 'Good' : 'Strong';
            return { ...this.requirements, score, passwordStrength: this.passwordStrength, passwordLabel: this.passwordLabel };
        }
    }">
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
        <h2 class="text-lg sm:text-xl md:text-2xl font-semibold text-[#273F4F] text-center mb-4 sm:mb-6 md:mb-8">Create your account</h2>
        <x-mary-form wire:submit="register">
            <x-mary-errors title="Oops!" description="Please, fix them." icon="o-face-frown" />
            
            <!-- First Name -->
            <div class="mb-4">
                <x-mary-input
                    wire:model="first_name"
                    placeholder="Enter your first name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="first_name"
                    x-on:blur="
                        const formatted = formatName($event.target.value);
                        $event.target.value = formatted;
                        $wire.first_name = formatted;
                        $nextTick(() => updateEmail());
                    " />
            </div>
            
            <!-- Last Name -->
            <div class="mb-4">
                <x-mary-input
                    wire:model="last_name"
                    placeholder="Enter your last name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="last_name"
                    x-on:blur="
                        const formatted = formatName($event.target.value);
                        $event.target.value = formatted;
                        $wire.last_name = formatted;
                        $nextTick(() => updateEmail());
                    " />
            </div>
            
            <!-- Email Address (Auto-filled, with suffix) -->
            <div class="mb-4">
                <x-mary-input
                    wire:model="email"
                    placeholder="Email is auto-generated from your name"
                    icon="o-envelope"
                    clearable
                    type="email"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="email" />
                <p class="text-xs text-gray-600 mt-1">Email is suggested from your name but you can edit it</p>
            </div>
            
            <!-- Password with Strength Meter -->
            <div class="mb-4">
                <x-mary-password
                    wire:model="password"
                    placeholder="Create a password"
                    required
                    autocomplete="new-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="password"
                    x-on:input="evaluatePassword($event.target.value)" />
                
                <!-- Password Strength Bar -->
                <div class="mt-2" role="status" aria-live="polite">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-600">Password Strength:</span>
                        <span class="text-xs font-semibold"
                              :class="{
                                  'text-red-600': passwordLabel === 'Weak',
                                  'text-orange-600': passwordLabel === 'Fair',
                                  'text-yellow-600': passwordLabel === 'Good',
                                  'text-green-600': passwordLabel === 'Strong'
                              }"
                              x-text="passwordLabel"></span>
                    </div>
                    <div class="w-full bg-gray-300 rounded-full h-2" role="progressbar" :aria-valuenow="passwordStrength" aria-valuemin="0" aria-valuemax="100" :aria-label="'Password strength: ' + passwordLabel">
                        <div class="h-2 rounded-full transition-all duration-300"
                             :class="{
                                 'bg-red-500': passwordLabel === 'Weak',
                                 'bg-orange-500': passwordLabel === 'Fair',
                                 'bg-yellow-500': passwordLabel === 'Good',
                                 'bg-green-500': passwordLabel === 'Strong'
                             }"
                             :style="'width: ' + passwordStrength + '%'"></div>
                    </div>
                </div>
                
                <!-- Password Requirements Checklist -->
                <div class="mt-3 space-y-1">
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
            
            <!-- Confirm Password -->
            <div class="mb-4">
                <x-mary-password
                    wire:model="password_confirmation"
                    placeholder="Confirm your password"
                    required
                    autocomplete="new-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="password_confirmation" />
            </div>
            
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0">
                <a class="underline text-xs sm:text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                   href="{{ route('login') }}" wire:navigate>
                    {{ __('Already registered?') }}
                </a>
                <x-primary-button class="sm:ml-auto order-1 sm:order-2 w-full sm:w-auto" wire:target="register"
                                  icon="o-user-plus">
                    {{ __('Register') }}
                </x-primary-button>
            </div>
        </x-mary-form>
    </div>
</div>
