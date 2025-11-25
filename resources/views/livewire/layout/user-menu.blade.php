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

    /**
     * Get unread notification count
     */
    public function getUnreadCountProperty()
    {
        return Auth::check() ? Auth::user()->unreadNotifications()->count() : 0;
    }

    /**
     * Get notification URL based on user role
     */
    public function getNotificationUrlProperty()
    {
        if (!Auth::check()) {
            return '#';
        }

        // Check if user has admin/librarian access
        if (Auth::user()->can('access-admin-dashboard')) {
            return route('admin.notifications');
        }

        return route('notifications');
    }
}; ?>

{{-- User Menu Dropdown - Compact version for mobile actions --}}
<div class="flex items-center gap-2">
    @auth
        {{-- Notification Bell Icon --}}
        <a href="{{ $this->notificationUrl }}" wire:navigate class="btn btn-ghost btn-circle relative">
            <x-mary-icon name="o-bell" class="w-5 h-5" />
            @if($this->unreadCount > 0)
                <span class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center"  wire:poll.3s="$refresh">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-error opacity-75"></span>
                    <span class="relative inline-flex h-5 w-5 items-center justify-center rounded-full bg-error text-white text-[10px] font-bold">
                        {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                    </span>
                </span>
            @endif
        </a>

        {{-- User Profile Dropdown --}}
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
