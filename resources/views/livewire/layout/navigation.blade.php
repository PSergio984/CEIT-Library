<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(): void
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-base-100 border-b border-base-300">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                {{-- Empty left side - breadcrumbs can go here if needed --}}
            </div>

            <!-- Settings Dropdown - Desktop Only -->
            <div class="flex items-center ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-3 border border-transparent text-sm leading-4 font-medium rounded-md text-base-content bg-base-100 hover:bg-base-200 focus:outline-none transition ease-in-out duration-150">
                            <x-mary-icon name="o-user-circle" class="w-8 h-8 sm:w-9 sm:h-9" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-base-300 bg-base-200">
                            <div class="font-medium text-sm text-base-content">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                            <div class="text-xs text-base-content/70">{{ auth()->user()->email }}</div>
                        </div>
                        <x-dropdown-link :href="route('profile')" wire:navigate class="hover:bg-base-200">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link class="hover:bg-base-200">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
