<?php

use App\Mail\Welcome;
use App\Models\User;
use App\Rules\PlvEmailDomain;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\']+$/u', // Letters, spaces, hyphens, apostrophes, Unicode support
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\']+$/u', // Letters, spaces, hyphens, apostrophes, Unicode support
            ],
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

        // Welcome email disabled - keeping code for future use if needed
        // Mail::to($user->email)->queue(new Welcome);

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
        // Form field tracking for validation
        fields: {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            password_confirmation: ''
        },
        touched: {
            first_name: false,
            last_name: false,
            email: false,
            password: false,
            password_confirmation: false
        },
        errors: {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            password_confirmation: ''
        },
        formatName(val) {
            if (!val) return '';
            // Trim, collapse multiple spaces, proper case each word
            val = val.trim().replace(/\s+/g, ' ');
            return val.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
        },
        updateEmail() {
            const cleanFirst = (this.$wire.first_name || '').replace(/\s+/g, '').toLowerCase();
            const cleanLast = (this.$wire.last_name || '').replace(/\s+/g, '').toLowerCase();
            this.$wire.email = (cleanFirst && cleanLast) ? cleanFirst + cleanLast + '@plv.edu.ph' : '';
            this.fields.email = this.$wire.email;
            this.validateField('email');
        },
        evaluatePassword(value) {
            this.requirements.length = value && value.length >= 8;
            this.requirements.number = /\d/.test(value || '');
            // Use single quotes for the string and escape only necessary characters in the regex
            // Avoid unescaped double quotes and problematic characters
            this.requirements.symbol = /[!@#$%^&*(),.?:{}|<>\[\]\\/]/.test(value || '');
            this.requirements.uppercase = /[A-Z]/.test(value || '');
            this.requirements.lowercase = /[a-z]/.test(value || '');
            const score = Object.values(this.requirements).filter(Boolean).length;
            this.passwordStrength = (score / 5) * 100;
            this.passwordLabel = score <= 2 ? 'Weak' : score === 3 ? 'Fair' : score === 4 ? 'Good' : 'Strong';
            this.fields.password = value;
            return { ...this.requirements, score, passwordStrength: this.passwordStrength, passwordLabel: this.passwordLabel };
        },
        validateField(field) {
            this.touched[field] = true;
            const value = this.fields[field] || '';
            
            switch(field) {
                case 'first_name':
                case 'last_name':
                    if (!value.trim()) {
                        this.errors[field] = 'This field is required.';
                    } else if (value.length < 2) {
                        this.errors[field] = 'Must be at least 2 characters.';
                    } else if (value.length > 50) {
                        this.errors[field] = 'Must not exceed 50 characters.';
                    } else if (!/^[\p{L}\s\-']+$/u.test(value)) {
                        this.errors[field] = 'Only letters, spaces, hyphens, and apostrophes allowed.';
                    } else {
                        this.errors[field] = '';
                    }
                    break;
                case 'email':
                    if (!value.trim()) {
                        this.errors.email = 'Email is required.';
                    } else if (!value.endsWith('@plv.edu.ph')) {
                        this.errors.email = 'Email must end with @plv.edu.ph';
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        this.errors.email = 'Please enter a valid email address.';
                    } else {
                        this.errors.email = '';
                    }
                    break;
                case 'password':
                    if (!value) {
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
                    break;
                case 'password_confirmation':
                    if (!value) {
                        this.errors.password_confirmation = 'Please confirm your password.';
                    } else if (value !== this.fields.password) {
                        this.errors.password_confirmation = 'Passwords do not match.';
                    } else {
                        this.errors.password_confirmation = '';
                    }
                    break;
            }
        },
        get isFormValid() {
            // All fields must be filled and have no errors
            const allFilled = this.fields.first_name && this.fields.last_name && 
                             this.fields.email && this.fields.password && 
                             this.fields.password_confirmation;
            const noErrors = !this.errors.first_name && !this.errors.last_name && 
                            !this.errors.email && !this.errors.password && 
                            !this.errors.password_confirmation;
            const passwordValid = this.requirements.length && this.requirements.uppercase && 
                                 this.requirements.lowercase && this.requirements.number && 
                                 this.requirements.symbol;
            const passwordsMatch = this.fields.password === this.fields.password_confirmation;
            return allFilled && noErrors && passwordValid && passwordsMatch;
        }
    }">
    <!-- Card Header with curve and logo -->
    <div class="relative z-20">
        <div class="bg-[#273F4F] h-24 rounded-t-2xl flex items-center justify-center overflow-hidden">
            <div class="absolute left-1/2 top-20 transform -translate-x-1/2 -translate-y-1/2 z-20">
                <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
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
                    x-on:input="fields.first_name = $event.target.value"
                    x-on:blur="
                        const formatted = formatName($event.target.value);
                        $event.target.value = formatted;
                        $wire.first_name = formatted;
                        fields.first_name = formatted;
                        validateField('first_name');
                        $nextTick(() => updateEmail());
                    " />
                <template x-if="touched.first_name && errors.first_name && !$wire.__instance.snapshot.memo.errors?.first_name">
                    <p class="text-red-500 text-xs mt-1" x-text="errors.first_name"></p>
                </template>
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
                    x-on:input="fields.last_name = $event.target.value"
                    x-on:blur="
                        const formatted = formatName($event.target.value);
                        $event.target.value = formatted;
                        $wire.last_name = formatted;
                        fields.last_name = formatted;
                        validateField('last_name');
                        $nextTick(() => updateEmail());
                    " />
                <template x-if="touched.last_name && errors.last_name && !$wire.__instance.snapshot.memo.errors?.last_name">
                    <p class="text-red-500 text-xs mt-1" x-text="errors.last_name"></p>
                </template>
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
                    error-field="email"
                    x-on:input="fields.email = $event.target.value"
                    x-on:blur="fields.email = $event.target.value; validateField('email')" />
                <p class="text-xs text-gray-600 mt-1">Email is suggested from your name but you can edit it</p>
                <template x-if="touched.email && errors.email && !$wire.__instance.snapshot.memo.errors?.email">
                    <p class="text-red-500 text-xs mt-1" x-text="errors.email"></p>
                </template>
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
                    x-on:input="evaluatePassword($event.target.value)"
                    x-on:blur="validateField('password')" />
                
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
                    error-field="password_confirmation"
                    x-on:input="fields.password_confirmation = $event.target.value"
                    x-on:blur="validateField('password_confirmation')" />
                <template x-if="touched.password_confirmation && errors.password_confirmation && !$wire.__instance.snapshot.memo.errors?.password_confirmation">
                    <p class="text-red-500 text-xs mt-1" x-text="errors.password_confirmation"></p>
                </template>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0">
                <a class="underline text-xs sm:text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                   href="{{ route('login') }}" wire:navigate>
                    {{ __('Already registered?') }}
                </a>
                <x-primary-button 
                    class="sm:ml-auto order-1 sm:order-2 w-full sm:w-auto" 
                    wire:target="register"
                    icon="o-user-plus"
                    x-bind:disabled="!isFormValid"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                    {{ __('Register') }}
                </x-primary-button>
            </div>
        </x-mary-form>
    </div>
</div>
