<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{

    public string $first_name = '';
    public string $last_name = '';
    public string $student_no = '';
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
            'student_no' => ['required', 'string', 'size:7', 'regex:/^\d{2}-\d{4}$/', 'unique:' . User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:' . User::class, 'regex:/^[A-Za-z0-9._%+-]+@plv\.edu\.ph$/'], // Email must end with plv.edu.ph
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative w-full max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg xl:max-w-2xl mx-auto">
    <!-- Card Header with curve and logo -->
    <div class="relative z-20">
        <div class="bg-[#273F4F] h-16 sm:h-20 md:h-24 rounded-b-xl sm:rounded-b-2xl flex items-center justify-center overflow-hidden">
            <div class="absolute left-1/2 top-12 sm:top-16 md:top-20 transform -translate-x-1/2 -translate-y-1/2 z-20">
                <img src="{{ asset('images/ceit-logo.png') }}" alt="CEIT Logo"
                     class="w-12 h-12 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-full border-2 sm:border-3 md:border-4 border-[#D9D9D9] bg-white shadow-lg object-contain">
            </div>
        </div>
    </div>
    <!-- Card Body -->
    <div class="bg-[#D9D9D9] rounded-b-xl sm:rounded-b-2xl pt-12 sm:pt-16 md:pt-20 pb-8 sm:pb-10 md:pb-12 px-4 sm:px-8 md:px-10 lg:px-14 shadow-2xl -mt-4 sm:-mt-6 md:-mt-8 relative z-10">
        <!-- Title -->
        <h2 class="text-lg sm:text-xl md:text-2xl font-semibold text-[#273F4F] text-center mb-4 sm:mb-6 md:mb-8">Create
            your account</h2>

        <x-mary-form wire:submit="register">
            <x-mary-errors title="Oops!" description="Please, fix them." icon="o-face-frown" />
            <!-- First Name -->
            <div class="mb-1 sm:mb-2">
                <x-mary-input
                    wire:model="first_name"
                    placeholder="Enter your first name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="first_name" />
            </div>

            <!-- Last Name -->
            <div class="mb-1 sm:mb-2">
                <x-mary-input
                    wire:model="last_name"
                    placeholder="Enter your last name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="last_name" />
            </div>

            <!-- Student Number -->
            <div class="mb-1 sm:mb-2">
                <x-mary-input
                    wire:model="student_no"
                    placeholder="Enter your student number"
                    icon="o-identification"
                    clearable
                    maxlength="7"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="student_no" />
            </div>

            <!-- Email Address -->
            <div class="mb-1 sm:mb-2">
                <x-mary-input
                    wire:model="email"
                    placeholder="Enter your email address"
                    icon="o-envelope"
                    clearable
                    type="email"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="email" />
            </div>

            <!-- Password -->
            <div class="mb-1 sm:mb-2">
                <x-mary-password
                    wire:model="password"
                    placeholder="Create a password"
                    required
                    autocomplete="new-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 !text-sm sm:!text-base"
                    icon-class="!text-gray-700"
                    error-field="password" />
            </div>

            <!-- Confirm Password -->
            <div class="mb-2 sm:mb-3">
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
                <a class="underline text-xs sm:text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 order-2 sm:order-1"
                   href="{{ route('login') }}" wire:navigate>
                    {{ __('Already registered?') }}
                </a>

                <x-mary-button
                    label="Register"
                    class="bg-slate-700 hover:bg-slate-800 text-white px-4 sm:px-6 md:px-8 py-2 text-sm sm:text-base rounded-lg order-1 sm:order-2 w-full sm:w-auto"
                    type="submit"
                    spinner="register"
                    icon="o-user-plus" />
            </div>
        </x-mary-form>
    </div>
</div>
