<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen font-sans antialiased bg-base-100">

    {{-- NAVBAR mobile only --}}
    <x-mary-nav sticky class="lg:hidden">
        <x-slot:brand>
            <label for="main-drawer" class="cursor-pointer">
                <x-mary-icon name="o-bars-3" class="w-6 h-6" />
            </label>
        </x-slot:brand>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-mary-theme-toggle darkTheme="fancychad" lightTheme="light" class="btn-sm" />

                {{-- Mobile Profile Dropdown --}}
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-2 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-base-content bg-base-100 hover:bg-base-200 focus:outline-none transition ease-in-out duration-150">
                            <x-mary-icon name="o-user-circle" class="w-6 h-6" />
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
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-start">
                                <x-dropdown-link class="hover:bg-base-200">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
                @endauth
            </div>
        </x-slot:actions>
    </x-mary-nav>

    {{-- MAIN --}}
    <x-mary-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200 border-r border-base-300">

            {{-- BRAND --}}
            <div class="px-4 py-3 flex items-center gap-3">
                <div class="flex-shrink-0">
                    <img src="{{ Vite::asset('public/images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo"/>
                </div>
                <div class="overflow-hidden transition-all duration-300 w-full" x-show="!collapsed">
                    <div class="font-bold text-lg text-base-content whitespace-nowrap">CEIT Library</div>
                </div>
            </div>

            <x-mary-menu-separator />

            {{-- MENU --}}
            <x-mary-menu activate-by-route>

                <x-mary-menu-sub title="Academic Papers" icon="o-book-open">
                    <x-mary-menu-item title="Information Technology" icon="o-computer-desktop" link="/admin/academic-papers/it" />
                    <x-mary-menu-item title="Civil Engineering" icon="o-building-office" link="/admin/academic-papers/civil" />
                    <x-mary-menu-item title="Electrical Engineering" icon="o-bolt" link="/admin/academic-papers/electrical" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="Rules & Regulations" icon="o-clipboard-document-list" link="/admin/rules" />
                <x-mary-menu-item title="Profile" icon="o-user" link="/profile" />
                <x-mary-menu-item title="Transactions" icon="o-archive-box" link="/transactions" />
                <x-mary-menu-item title="Credit Score History" icon="o-chart-bar" link="/credit-score-history" />

            </x-mary-menu>

        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            {{-- Desktop Navigation --}}
            <div class="hidden lg:block">
                <nav class="bg-base-100 border-b border-base-300">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-end items-center h-16 gap-2">
                            <x-mary-theme-toggle darkTheme="fancychad" lightTheme="light" class="btn-sm" />
                            <livewire:layout.navigation />
                        </div>
                    </div>
                </nav>
            </div>
            {{ $slot }}

            <!-- Footer -->
            <footer class="bg-base-100 border-t border-base-300 mt-auto">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-sm text-base-content/70">
                        <p>&copy; {{ date('Y') }} PLV eLib - CEIT Library Management System</p>
                        <p class="mt-1">Pamantasan ng Lungsod ng Valenzuela</p>
                    </div>
                </div>
            </footer>
        </x-slot:content>

    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />

    @livewireScripts
</body>
</html>
