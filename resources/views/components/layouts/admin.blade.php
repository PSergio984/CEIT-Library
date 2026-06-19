<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/ceit-logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen font-sans antialiased bg-background text-foreground">

    {{-- NAVBAR mobile only --}}
    <x-mary-nav sticky class="lg:hidden">
        <x-slot:brand>
            {{-- Hamburger menu button --}}
            <label for="main-drawer" class="btn btn-square btn-ghost lg:hidden mr-2">
                <x-mary-icon name="o-bars-3" class="w-5 h-5" />
            </label>
            <div>CEIT Library</div>
        </x-slot:brand>
        <x-slot:actions>
            {{-- Mobile User Dropdown --}}
            <livewire:layout.user-menu />
        </x-slot:actions>
    </x-mary-nav>
    {{-- MAIN --}}
    <x-mary-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">
            @persist('admin-sidebar')
            {{-- BRAND --}}
            <div class="flex items-center justify-center py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <a href="/dashboard">
                            <img 
                                src="{{ Vite::asset('resources/images/ceit-logo.png') }}" 
                                class="h-10 w-10" 
                                alt="CEIT Logo"
                            >
                        </a>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="overflow-hidden transition-all duration-300" x-show="!collapsed">
                            <div class="font-bold text-xl text-base-content whitespace-nowrap">CEIT Library</div>
                        </div>
                        {{-- Theme Toggle beside Library text --}}
                        <div x-show="!collapsed">
                            <x-mary-theme-toggle class="btn btn-sm btn-circle btn-ghost" />
                        </div>
                    </div>
                </div>
            </div>


            {{-- MENU --}}
            <x-mary-menu activate-by-route class="[&_.mary-menu-sub]:!pl-0 [&_.mary-menu-item]:!pl-0">

                <x-mary-menu-item title="Dashboard" icon="o-home" link="{{ route('admin.dashboard') }}" tooltip="Dashboard" wire:navigate.hover />
 
                @can('view-academic-papers')
                    <x-mary-menu-item title="Academic Papers" icon="o-book-open" link="{{ route('admin.academic-paper.index') }}" tooltip="Academic Papers" wire:navigate.hover />
                @endcan
 
                @can('manage-advisers-deans')
                    <x-mary-menu-item title="Advisers & Deans" icon="o-academic-cap" link="{{ route('admin.advisers-deans') }}" tooltip="Advisers & Deans" wire:navigate.hover />
                @endcan
 
                <x-mary-menu-item title="Borrow Logs" icon="o-archive-box-arrow-down" link="{{ route('admin.borrow-logs') }}" tooltip="Borrow Logs" wire:navigate.hover />
                
                {{-- Notifications with Badge --}}
                <x-mary-menu-item title="Notifications" icon="o-bell" link="{{ route('admin.notifications') }}" tooltip="Notifications" wire:navigate.hover>
                    @if(auth()->check() && auth()->user()->unreadNotifications()->count() > 0)
                        <x-slot:actions>
                            <span class="badge badge-primary badge-sm">{{ auth()->user()->unreadNotifications()->count() }}</span>
                        </x-slot:actions>
                    @endif
                </x-mary-menu-item>
 
                {{-- @can('manage-students')
                    <x-mary-menu-item title="Students" icon="o-academic-cap" link="/admin/students" />
                @endcan --}}
 
                @can('manage-librarian-batches')
                    <x-mary-menu-item title="Librarian Batches" icon="o-building-library" link="{{ route('admin.librarians') }}" wire:navigate.hover />
                @endcan
 
                @can('manage-user-roles')
                    <x-mary-menu-item title="Manage Roles" icon="o-shield-check" link="{{ route('admin.manage-roles') }}" wire:navigate.hover />
                @endcan
 
                <x-mary-menu-item title="Rules & Regulations" icon="o-clipboard-document-list"
                    link="{{ route('admin.rules-and-regulations.index') }}" wire:navigate.hover />
 
                @can('view-attendance-logs')
                    <x-mary-menu-item title="Attendance" icon="o-user-group" link="{{ route('admin.attendance-logs') }}" wire:navigate.hover />
                @endcan
 
                @can('view-violation-logs')
                    <x-mary-menu-item title="Violations" icon="o-exclamation-triangle" link="{{ route('admin.violation-logs') }}" wire:navigate.hover />
                @endcan
 
                <x-mary-menu-item title="View as Student" icon="o-eye" link="{{ route('dashboard') }}" wire:navigate.hover />

            </x-mary-menu>
            @endpersist
        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            <div class="flex flex-col min-h-screen pb-16 lg:pb-0">
                {{-- Desktop Navigation --}}
                <div class="hidden lg:block">
                    <livewire:layout.navigation />
                </div>

                {{-- The `$slot` goes here --}}
                <div class="flex-1 bg-base-100">
                    {{ $slot }}
                </div>
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- BOTTOM NAVIGATION (Mobile only) --}}
    <div id="mobile-nav" class="lg:hidden fixed bottom-0 left-0 right-0 z-[9999] bg-base-100 border-t border-base-300 h-16 shadow-2xl">
        <div class="flex justify-around items-center h-full">
            <a href="{{ route('admin.dashboard') }}"
                wire:navigate.hover
                class="flex flex-col items-center justify-center w-full h-full
                    {{ request()->routeIs('admin.dashboard')
                        ? 'text-primary'
                        : 'text-base-content/70' }}"
            >
                <x-mary-icon name="o-home" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">Home</span>
            </a>
            
            @can('view-academic-papers')
            <a href="{{ route('admin.academic-paper.index') }}"
                wire:navigate.hover
                class="flex flex-col items-center justify-center w-full h-full
                    {{ request()->routeIs('admin.academic-paper.index', 'admin.academic-paper.show', 'admin.academic-paper.create', 'admin.academic-paper.edit')
                        ? 'text-primary'
                        : 'text-base-content/70' }}"
            >
                <x-mary-icon name="o-book-open" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">Papers</span>
            </a>
            @endcan
            
            @can('view-attendance-logs')
            <a href="{{ route('admin.attendance', ['scan' => 1]) }}"
                wire:navigate.hover
                class="flex flex-col items-center justify-center w-full h-full
                    {{ request()->routeIs('admin.attendance', 'admin.attendance-logs')
                        ? 'text-primary'
                        : 'text-base-content/70' }}"
            >
                <div class="bg-primary text-primary-content p-3 rounded-full -mt-8 shadow-lg border-4 border-base-100">
                    <x-mary-icon name="o-qr-code" class="w-7 h-7" />
                </div>
                <span class="text-[10px] mt-1 font-bold text-primary">Scan</span>
            </a>
            @endcan

            <a href="{{ route('admin.notifications') }}"
                wire:navigate.hover
                class="flex flex-col items-center justify-center w-full h-full
                    {{ request()->routeIs('admin.notifications')
                        ? 'text-primary'
                        : 'text-base-content/70' }}"
            >
                <div class="relative">
                    <x-mary-icon name="o-bell" class="w-6 h-6" />
                    @if(auth()->check() && auth()->user()->unreadNotifications()->count() > 0)
                        <span class="badge badge-primary badge-xs absolute -top-1 -right-1"></span>
                    @endif
                </div>
                <span class="text-[10px] mt-1 font-medium">Alerts</span>
            </a>
            
            <label for="main-drawer" class="flex flex-col items-center justify-center w-full h-full text-base-content/70 cursor-pointer">
                <x-mary-icon name="o-ellipsis-horizontal" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">More</span>
            </label>
        </div>
    </div>

    {{-- Toast --}}
    <x-mary-toast />

    @livewireScripts

</body>

</html>
