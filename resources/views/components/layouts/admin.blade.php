<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('images/ceit-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen font-sans antialiased bg-background text-foreground">

    {{-- NAVBAR mobile only --}}
    <x-mary-nav sticky class="lg:hidden">
        <x-slot:brand>
            <label for="main-drawer" class="cursor-pointer">
                <x-mary-icon name="o-bars-3" class="w-6 h-6" />
            </label>
        </x-slot:brand>
        <x-slot:actions>
            {{-- Mobile User Dropdown --}}
            <livewire:layout.navigation />
        </x-slot:actions>
    </x-mary-nav>

    {{-- MAIN --}}
    <x-mary-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <div class="flex items-center justify-center py-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <img src="{{ asset('images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo"/>
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

            <x-mary-menu-separator />

            {{-- MENU --}}
            <x-mary-menu activate-by-route class="[&_.mary-menu-sub]:!pl-0 [&_.mary-menu-item]:!pl-0">

                <x-mary-menu-item title="Dashboard" icon="o-home" link="/admin/dashboard" />

                <x-mary-menu-sub title="Academic Papers" icon="o-book-open">
                    <x-mary-menu-item title="All Academic Paper" icon="o-document-text" link="/admin/academic-papers" />
                    <x-mary-menu-item title="Information Technology" icon="o-computer-desktop" link="/admin/academic-papers/it" />
                    <x-mary-menu-item title="Civil Engineering" icon="o-building-office" link="/admin/academic-papers/ce" />
                    <x-mary-menu-item title="Electrical Engineering" icon="o-bolt" link="/admin/academic-papers/ee" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="Borrow Logs" icon="o-archive-box-arrow-down" link="/admin/transactions" />

                <x-mary-menu-item title="Students" icon="o-academic-cap" link="/admin/students" />
                <x-mary-menu-sub title="Librarians" icon="o-building-library">
                    <x-mary-menu-item title="Current" icon="o-user" link="/admin/librarians" />
                    <x-mary-menu-item title="Assign New" icon="o-user-plus" link="/admin/librarians/assign" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="Attendance" icon="o-user-group" link="/admin/attendance" />
                <x-mary-menu-item title="Violation Logs" icon="o-shield-exclamation" link="/admin/violation-logs" />
                <x-mary-menu-item title="View as Student" icon="o-eye" link="/academic-papers" />

            </x-mary-menu>

        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            {{-- Desktop Navigation --}}
            <div class="hidden lg:block">
                <livewire:layout.navigation />
            </div>
            {{ $slot }}
        </x-slot:content>

    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />

    @livewireScripts

</body>

</html>
