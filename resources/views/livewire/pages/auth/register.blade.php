<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use WithFileUploads;

    public string $firstName = '';
    public string $lastName = '';
    public string $studentNo = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public $idImg;
    public $idPath;


    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'studentNo' => ['required', 'string', 'max:7'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:50', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'idImg' => ['required', 'image', 'max:4096'],
        ]);

        // Store the file using $this->idImg and get the path
        if ($this->idImg) {
            $validated['id_path'] = $this->idImg->storePublicly('id_images', ['disk' => 'public']);
        }

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative w-full max-w-2xl mx-auto">
    <!-- Card Header with curve and logo -->
    <div class="relative z-20">
        <div class="bg-[#273F4F] h-24 rounded-b-2xl  flex items-center justify-center overflow-hidden">
            <div class="absolute left-1/2 top-20 transform -translate-x-1/2 -translate-y-1/2 z-20">
                <img src="{{ asset('images/ceit-logo.png') }}" alt="Description of image" class="w-20 h-20 rounded-full border-4 border-[#D9D9D9] bg-white shadow-lg">
            </div>
        </div>
        <!-- More aggressive bottom curve -->
        <div class="w-full h-20 bg-[#273F4F] rounded-b-[200px] -mt-10"></div>
    </div>
    <!-- Card Body -->
    <div class="bg-[#D9D9D9] rounded-b-2xl pt-20 pb-12 px-14 shadow-2xl -mt-8 relative z-10">
        <!-- Title -->
        <h2 class="text-2xl font-semibold text-gray-800 text-center mb-8">Create your account</h2>

        <x-mary-form wire:submit="register">
            <x-mary-errors title="Oops!" description="Please, fix them." icon="o-face-frown" />

            <!-- First Name -->
            <div class="mb-1">
                <x-mary-input
                    wire:model="firstName"
                    placeholder="Enter your first name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="firstName" />
            </div>

            <!-- Last Name -->
            <div class="mb-1">
                <x-mary-input
                    wire:model="lastName"
                    placeholder="Enter your last name"
                    icon="o-user"
                    clearable
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="lastName" />
            </div>

            <!-- Student Number -->
            <div class="mb-1">
                <x-mary-input
                    wire:model="studentNo"
                    placeholder="Enter your student number"
                    icon="o-identification"
                    clearable
                    maxlength="7"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="studentNo" />
            </div>

            <!-- Email Address -->
            <div class="mb-2">
                <x-mary-input
                    wire:model="email"
                    placeholder="Enter your email address"
                    icon="o-envelope"
                    clearable
                    type="email"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="email" />
            </div>

            <!-- Password -->
            <div class="mb-2">
                <x-mary-password
                    wire:model="password"
                    placeholder="Create a password"
                    required
                    autocomplete="new-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="password" />
            </div>

            <!-- Confirm Password -->
            <div class="mb-2">
                <x-mary-password
                    wire:model="password_confirmation"
                    placeholder="Confirm your password"
                    required
                    autocomplete="new-password"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600"
                    icon-class="!text-gray-700"
                    error-field="password_confirmation" />
            </div>

            <!-- ID Image Upload -->
            <div class="mb-2">
                <x-mary-file
                    wire:model="idImg"
                    hint="Upload your ID image, only image files are allowed."
                    accept="image/png, image/jpeg"
                    class="!bg-[#D9D9D9] !border-gray-400 !text-black placeholder:!text-gray-600 file:!bg-[#D9D9D9] file:!text-black file:!border-gray-400 file:!rounded-md"
                    icon-class="!text-gray-700"
                    label-class="!text-black"
                    hint-class="!text-black" />

                @if($idImg && method_exists($idImg, 'temporaryUrl'))
                    <img src="{{ $idImg->temporaryUrl() }}" alt="Preview"
                         class="w-1/2 h-32 object-cover m-2 rounded-lg">
                @endif
            </div>

            <div class="flex items-center justify-between">
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                   href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-mary-button
                    label="Register"
                    class="bg-slate-700 hover:bg-slate-800 text-white px-8 py-2 rounded-lg"
                    type="submit"
                    spinner="register"
                    icon="o-user-plus" />
            </div>
        </x-mary-form>
    </div>
</div>
