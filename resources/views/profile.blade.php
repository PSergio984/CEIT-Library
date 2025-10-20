<x-layouts.app>
    @section('title', 'Profile')

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-base-100 shadow-lg sm:rounded-lg text-base-content">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg text-base-content font-bold">
                                {{ __('Profile Information') }}
                            </h2>
                            <p class="mt-1 text-sm text-base-content">
                                {{ __('Your account profile information.') }}
                            </p>
                        </header>

                        <div class="mt-6 space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-base-content">{{ __('Full Name') }}</label>
                                <div class="mt-1 p-3 bg-base-200 rounded-lg border border-base-300">
                                    <span class="text-base-content font-medium">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-base-content">{{ __('Email Address') }}</label>
                                <div class="mt-1 p-3 bg-base-200 rounded-lg border border-base-300">
                                    <span class="text-base-content font-medium">{{ auth()->user()->email }}</span>
                                </div>
                            </div>

                        </div>
                    </section>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-base-100 shadow-lg sm:rounded-lg text-base-content">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            {{-- Delete user form commented out - not needed for viewing only profile --}}
            {{--
            <div class="p-4 sm:p-8 bg-base-100 shadow-lg sm:rounded-lg text-base-content">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
            --}}

            {{-- Attendance QR Livewire Component --}}
            <livewire:pages.student.attendance-qr/>

        </div>
    </div>
</x-layouts.app>
