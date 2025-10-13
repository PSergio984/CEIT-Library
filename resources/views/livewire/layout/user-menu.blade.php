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

{{-- User Menu Dropdown - Compact version for mobile actions --}}
<div class="flex items-center gap-2">
    @auth
        <x-dropdown align="right" width="48" contentClasses="py-1 bg-base-100">
            <x-slot name="trigger">
                <x-mary-button icon="o-user" class="btn-ghost btn-circle" />
            </x-slot>

            <x-slot name="content" class="bg-base-100 border-base-300">
                <div class="px-4 py-2 border-base-300 bg-base-200">
                    <div class="font-medium text-sm text-base-content">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                    <div class="text-xs text-base-content opacity-70">{{ auth()->user()->email }}</div>
                </div>
                
                <x-dropdown-link :href="route('profile')" wire:navigate >
                    {{ __('Profile') }}
                </x-dropdown-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-dropdown-link>
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </button>
            </x-slot>
        </x-dropdown>
    @else
        <a href="{{ route('login') }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-foreground bg-background hover:bg-muted focus:outline-none transition ease-in-out duration-150">
            {{ __('Login') }}
        </a>
    @endauth
</div>
