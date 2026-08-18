<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
class extends Component
{
    public LoginForm $form;

    public function rendering(\Illuminate\View\View $view): void
    {
        $view->title('Login - ' . config('branding.name'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        // Check if user is super admin or has admin access and redirect accordingly
        $user = auth()->user();
        if ($user && ($user->isSuperAdmin() || $user->hasAdminAccess())) {
            $this->redirectIntended(default: route('admin.dashboard', absolute: false), navigate: true);
        } else {
            $this->redirectIntended(default: route('student.dashboard', absolute: false), navigate: true);
        }
    }

    /**
     * One-click demo login with the seeded student account
     * (DatabaseSeeder: student@plv.edu.ph / Pwd@12345). Exists so
     * reviewers can try the app without creating an account.
     */
    public function demoLogin(): void
    {
        $this->form->email = 'student@plv.edu.ph';
        $this->form->password = 'Pwd@12345';

        $this->login();
    }
}; ?>

<div class="relative w-full max-w-2xl mx-auto" x-data="{
    fields: {
        email: '',
        password: ''
    },
    touched: {
        email: false,
        password: false
    },
    errors: {
        email: '',
        password: ''
    },
    validateField(field) {
        this.touched[field] = true;
        const value = this.fields[field] || '';
        
        switch(field) {
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
                } else {
                    this.errors.password = '';
                }
                break;
        }
    },
    get isFormValid() {
        return this.fields.email && 
               this.fields.email.trim().length > 0 &&
               this.fields.email.endsWith('@plv.edu.ph') &&
               this.fields.password && 
               this.fields.password.trim().length > 0 &&
               !this.errors.email && 
               !this.errors.password;
    }
}">
    <!-- Clean header and logo -->
    <div class="flex flex-col items-center justify-center mb-6">
        <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo"
             class="w-20 h-20 rounded-full border-4 border-slate-200/50 dark:border-white/10 bg-white dark:bg-slate-800 shadow-lg mb-4">
        <h2 class="text-xl sm:text-2xl font-extrabold text-[#0046ad] dark:text-sky-400 text-center tracking-tight">Log in to your account</h2>
    </div>

    <!-- Card Body Content -->
    <div class="w-full px-2 sm:px-6">
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />
        
        <!-- Email Verified Message -->
        @if (session('verified'))
            <div class="mb-4 p-4 bg-blue-50/50 dark:bg-blue-950/30 border border-blue-200/50 dark:border-blue-800/30 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700 dark:text-blue-300">{{ session('verified') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <x-mary-form wire:submit="login">
            <x-mary-errors title="Oops!" description="Please, fix them." icon="o-face-frown"/>

            <!-- Email Address -->
            <div class="mb-4 text-left">
                <x-mary-input
                    wire:model="form.email"
                    placeholder="Email"
                    icon="o-envelope"
                    clearable
                    type="email"
                    class="!bg-white dark:!bg-slate-800 !border-gray-300 dark:!border-slate-700 !text-slate-900 dark:!text-white placeholder:!text-slate-500 dark:placeholder:!text-slate-400 !text-sm sm:!text-base"
                    icon-class="!text-gray-700 dark:!text-slate-300"
                    required
                    autofocus
                    autocomplete="username"
                    error-field="form.email"
                    x-on:input="fields.email = $event.target.value"
                    x-on:blur="validateField('email')"/>
                <template x-if="touched.email && errors.email && !$wire.__instance.snapshot.memo.errors?.['form.email']">
                    <p class="text-red-600 dark:text-red-400 text-xs mt-1" x-text="errors.email"></p>
                </template>
            </div>

            <!-- Password -->
            <div class="mb-4 text-left">
                <x-mary-password
                    wire:model="form.password"
                    placeholder="Password"
                    required
                    autocomplete="current-password"
                    class="!bg-white dark:!bg-slate-800 !border-gray-300 dark:!border-slate-700 !text-slate-900 dark:!text-white placeholder:!text-slate-500 dark:placeholder:!text-slate-400 !text-sm sm:!text-base"
                    icon-class="!text-gray-700 dark:!text-slate-300"
                    error-field="form.password"
                    x-on:input="fields.password = $event.target.value"
                    x-on:blur="validateField('password')"/>
                <template x-if="touched.password && errors.password && !$wire.__instance.snapshot.memo.errors?.['form.password']">
                    <p class="text-red-600 dark:text-red-400 text-xs mt-1" x-text="errors.password"></p>
                </template>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between mb-6">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox"
                           class="rounded border-gray-300 dark:border-slate-700 text-indigo-600 shadow-sm focus:ring-indigo-500 bg-white dark:bg-slate-800"
                           name="remember">
                    <span class="ms-2 text-sm text-slate-700 dark:text-slate-300">{{ __('Remember me') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm text-[#0046ad] dark:text-sky-400 hover:underline font-medium"
                       href="{{ route('password.request') }}" wire:navigate>
                        Forgot your password?
                    </a>
                @endif
            </div>

            <!-- Demo login -->
            <div class="mb-4 rounded-xl border border-dashed border-[#0046ad]/30 dark:border-sky-400/30 bg-[#0046ad]/5 dark:bg-sky-400/5 p-3">
                <p class="text-xs text-slate-600 dark:text-slate-300 mb-2">Reviewer demo: signs in with the seeded student account
                    (<span class="font-mono">student@plv.edu.ph</span>).</p>
                <button type="button" wire:click="demoLogin" wire:loading.attr="disabled" wire:target="demoLogin"
                        class="w-full rounded-xl border border-[#0046ad] text-[#0046ad] dark:border-sky-400 dark:text-sky-400 py-2 text-sm font-semibold hover:bg-[#0046ad]/10 dark:hover:bg-sky-400/10 transition-colors">
                    {{ __('Log in with demo student') }}
                </button>
            </div>

            <!-- Login Button -->
            <div class="mb-4 flex justify-center">
                <x-primary-button 
                    class="w-full sm:w-2/3 md:w-1/2 bg-[#0046ad] hover:bg-[#003da0] text-white border-none rounded-xl py-3 dark:bg-sky-600 dark:hover:bg-sky-500" 
                    wire:target="login" 
                    wire:loading.attr="disabled"
                    x-bind:disabled="!isFormValid"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>

            <!-- Register Link -->
            <div class="text-center mt-4">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-[#0046ad] dark:text-sky-400 hover:underline font-medium"
                       wire:navigate>
                        Register
                    </a>
                </p>
            </div>
        </x-mary-form>
    </div>
</div>
