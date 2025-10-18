<?php

use App\Mail\Welcome;
use App\Models\User;
use App\Rules\PlvEmailDomain;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
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
            'last_name' => [
                'required', 
                'string', 
                'max:255', 
                function ($attribute, $value, $fail) {
                    // Get email from the current form data
                    $email = $this->email ?? '';
                    $firstName = $this->first_name ?? '';
                    $lastName = $value;

                    if (!$email) {
                        return; // Skip validation if no email provided
                    }

                    // Extract the email prefix (part before @plv.edu.ph)
                    if (!preg_match('/^(.+)@plv\.edu\.ph$/', $email, $matches)) {
                        return; // Skip if email doesn't match PLV format
                    }

                    $emailPrefix = strtolower($matches[1]);

                    // Only validate when we have both names
                    if (empty($firstName) || empty($lastName)) {
                        return;
                    }

                    // Concatenate both names and normalize (remove spaces and convert to lowercase)
                    $concatenatedName = strtolower(str_replace(' ', '', $firstName . $lastName));

                    // Check if concatenated name matches email prefix
                    if ($concatenatedName !== $emailPrefix) {
                        $fail('The first name and last name must match the characters before @plv.edu.ph in your email address. Expected: ' . $emailPrefix . ', Got: ' . $concatenatedName);
                    }
                }
            ],
            'email' => ['required', 'string', 'lowercase', new PlvEmailDomain, 'email', 'max:100', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
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

<div class="relative w-full max-w-2xl mx-auto">
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
        <!-- Title -->
        <h2 class="text-lg sm:text-xl md:text-2xl font-semibold text-[#273F4F] text-center mb-4 sm:mb-6 md:mb-8">Create
            your account</h2>
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
                    error-field="first_name" />
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
                    error-field="last_name" />
            </div>
            <!-- Email Address -->
            <div class="mb-4">
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
            <div class="mb-4">
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
