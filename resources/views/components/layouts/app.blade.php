<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" data-theme="light">

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
            <div class="ml-5 pt-5">App</div>
        </x-slot:brand>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <label for="main-drawer" class="lg:hidden">
                    <x-heroicon-s-home-modern />
                </label>

            </div>
        </x-slot:actions>
    </x-mary-nav>
    {{-- MAIN --}}
    <x-mary-main full-width>
        <div class="flex">
            {{-- SIDEBAR --}}
            <div>
                <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-300">

                    {{-- BRAND --}}
                    <div class="ml-4 pt-5 flex items-center justify-between">
                        <img src="{{ Vite::asset('public/images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo" />
                        <div class="flex-1 flex items-center justify-between transition-all duration-300"
                            x-show="!collapsed">
                            <div>CEIT Library</div>
                            <x-mary-theme-toggle />
                        </div>
                    </div>


                    {{-- MENU --}}
                    <x-mary-menu activate-by-route>
                        <x-mary-menu-sub title="Academic Paper" icon="o-book-open">
                            <x-mary-menu-item title="Information Technology" icon="o-computer-desktop"
                                link="/admin/academic-papers/it" />
                            <x-mary-menu-item title="Civil Engineering" icon="o-building-office"
                                link="/admin/academic-papers/civil" />
                            <x-mary-menu-item title="Electrical Engineering" icon="o-bolt"
                                link="/admin/academic-papers/electrical" />
                        </x-mary-menu-sub>
                        <x-mary-menu-item title="Rules & Regulations" icon="o-clipboard-document-list"
                            link="/admin/rules" />
                        <x-mary-menu-item title="Profile" icon="o-user" link="/profile" />
                        <x-mary-menu-item title="Transaction" icon="o-archive-box" link="/transactions" />
                        <x-mary-menu-item title="Credit Score History" icon="o-exclamation-triangle"
                            link="/credit-score-history" />
                    </x-mary-menu>
                </x-slot:sidebar>
            </div>

            {{-- The `$slot` goes here --}}
            <div class="flex-1 bg-base-100">
                <x-slot:content>
                    <livewire:layout.navigation />
                    {{ $slot }}

                    <!-- Simple Footer -->
                    <footer class="bg-base-100 border-t border-base-300">
                        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                            <div class="text-center text-sm text-base-content">
                                <p>&copy; {{ date('Y') }} PLV eLib - CEIT Library Management System</p>
                                <p class="mt-1">Pamantasan ng Lungsod ng Valenzuela</p>
                            </div>
                        </div>
                    </footer>
                </x-slot:content>
            </div>

        </div>
    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />
</body>

</html>
