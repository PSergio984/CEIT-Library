<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="ceit">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', $title ?? config('app.name'))</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ Vite::asset('public/images/ceit-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen font-sans antialiased bg-background text-foreground flex flex-col">

    {{-- NAVBAR mobile only --}}
    <x-mary-nav sticky class="lg:hidden">
        <x-slot:brand>
            <label for="main-drawer" class="cursor-pointer">
                <x-mary-icon name="o-bars-3" class="w-6 h-6" />
            </label>
        </x-slot:brand>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-mary-theme-toggle darkTheme="ceit" lightTheme="ceit-light" class="btn-sm"/>

                {{-- Mobile Profile Dropdown --}}
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-2 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-foreground bg-card hover:bg-muted focus:outline-none transition ease-in-out duration-150">
                            <x-mary-icon name="o-user-circle" class="w-6 h-6" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-border bg-muted">
                            <div
                                class="font-medium text-sm text-foreground">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                            <div class="text-xs text-muted-foreground">{{ auth()->user()->email }}</div>
                        </div>
                        <x-dropdown-link :href="route('profile')" wire:navigate class="hover:bg-muted">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-start">
                                <x-dropdown-link class="hover:bg-muted">
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
    <x-mary-main full-width class="flex-1 flex flex-col">
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-sidebar border-r border-sidebar-border">

            {{-- BRAND --}}
            <div class="px-4 py-3 flex items-center gap-3">
                <div class="flex-shrink-0">
                    <img src="{{ Vite::asset('public/images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo"/>
                </div>
                <div class="overflow-hidden transition-all duration-300 w-full" x-show="!collapsed">
                    <div class="font-bold text-lg text-sidebar-foreground whitespace-nowrap">CEIT Library</div>
                </div>
            </div>

            <x-mary-menu-separator />

            {{-- MENU --}}
            <x-mary-menu activate-by-route>

                <x-mary-menu-sub title="Academic Papers" icon="o-book-open">
                    <x-mary-menu-item title="All" icon="o-document-text" link="/academic-papers" />
                    <x-mary-menu-item title="Information Technology" icon="o-computer-desktop" link="/academic-papers/it" />
                    <x-mary-menu-item title="Civil Engineering" icon="o-building-office" link="/academic-papers/ce" />
                    <x-mary-menu-item title="Electrical Engineering" icon="o-bolt" link="/academic-papers/ee" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="Rules & Regulations" icon="o-clipboard-document-list" link="/admin/rules" />
                <x-mary-menu-item title="Profile" icon="o-user" link="/profile" />
                <x-mary-menu-item title="Transactions" icon="o-archive-box" link="/transactions" />
                <x-mary-menu-item title="Credit Score History" icon="o-chart-bar" link="/credit-score-history" />

            </x-mary-menu>

        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            <div class="flex flex-col min-h-screen">
                {{-- Desktop Navigation --}}
                <div class="hidden lg:block">
                    <nav class="bg-background border-b border-border">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="flex justify-end items-center h-16 gap-2">
                                <x-mary-theme-toggle darkTheme="ceit" lightTheme="ceit-light" class="btn-sm"/>
                                <livewire:layout.navigation />
                            </div>
                        </div>
                    </nav>
                </div>
                
                <div class="flex-1">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <footer class="bg-background border-t border-border mt-auto">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        <div class="text-center text-sm text-muted-foreground">
                            <p>&copy; {{ date('Y') }} PLV eLib - CEIT Library Management System</p>
                            <p class="mt-1">Pamantasan ng Lungsod ng Valenzuela</p>
                        </div>
                    </div>
                </footer>
            </div>
        </x-slot:content>

    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />

    @livewireScripts
</body>
</html>
