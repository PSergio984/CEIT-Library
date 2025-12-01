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
                    <livewire:profile.update-profile-information-form />
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
