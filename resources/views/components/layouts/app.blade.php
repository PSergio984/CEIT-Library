<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', $title ?? config('app.name'))</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('images/ceit-logo.png') }}">

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
    <x-mary-main full-width class="flex-1 flex flex-col">
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <div class="flex items-center justify-center py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <img src="{{ asset('images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo"/>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class=" transition-all duration-300" x-show="!collapsed">
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

                <x-mary-menu-sub title="Academic Papers" icon="o-book-open">
                    <x-mary-menu-item title="All" icon="o-document-text" link="/academic-papers" />
                    <x-mary-menu-item title="Information Technology" icon="o-computer-desktop" link="/academic-papers/it" />
                    <x-mary-menu-item title="Civil Engineering" icon="o-building-office" link="/academic-papers/ce" />
                    <x-mary-menu-item title="Electrical Engineering" icon="o-bolt" link="/academic-papers/ee" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="Rules & Regulations" icon="o-clipboard-document-list" link="/rule-and-regulation" />
                <x-mary-menu-item title="Profile" icon="o-user" link="/profile" />
                <x-mary-menu-item title="Transactions" icon="o-archive-box" link="/transactions" />
                <x-mary-menu-item title="Credit Score History" icon="o-chart-bar" link="/credit-score-history" />
                @can('Admin-access')
                    <x-mary-menu-item title="Admin Dashboard" icon="o-squares-2x2" link="/admin/academic-papers" />
                @endcan

            </x-mary-menu>

        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            <div class="flex flex-col min-h-screen">
                {{-- Desktop Navigation --}}
                <div class="hidden lg:block">
                    <livewire:layout.navigation />
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

        </div>
    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />
</body>

</html>
