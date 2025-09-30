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
    <body class="min-h-screen font-sans antialiased bg-base-100 ">

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
                <x-slot:sidebar drawer="main-drawer" collapsible  class="bg-base-300">

                    {{-- BRAND --}}
                    <div class="ml-4 pt-5 flex items-center justify-between">
                        <img src="{{ Vite::asset('public/images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo"/>
                        <div class="flex-1 flex items-center justify-between transition-all duration-300" x-show="!collapsed">
                            <div>CEIT Library</div>
                            <x-mary-theme-toggle/>
                        </div>
                    </div>


                    {{-- MENU --}}
                    <x-mary-menu activate-by-route>

                        {{-- User --}}
                        @if($user = auth()->user())
                            <x-mary-menu-separator />

                            <x-mary-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                                <x-slot:actions>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-mary-button
                                            icon="o-power"
                                            class="btn-circle btn-ghost btn-xs"
                                            tooltip-left="Logoff"
                                            type="submit" />
                                    </form>
                                </x-slot:actions>
                            </x-mary-list-item>

                            <x-mary-menu-separator />
                        @endif

                        <x-mary-menu-item title="Dashboard" icon="o-home" link="/admin/dashboard" />
                        <x-mary-menu-sub title="Academic Paper List" icon="o-book-open">
                            <x-mary-menu-item title="Information Technology" icon="o-computer-desktop" link="/admin/academic-papers/it" />
                            <x-mary-menu-item title="Civil Engineering" icon="o-building-office" link="/admin/academic-papers/civil-engineering" />
                            <x-mary-menu-item title="Electrical Engineering" icon="o-bolt" link="/admin/academic-papers/electrical-engineering" />
                        </x-mary-menu-sub>
                        <x-mary-menu-item title="Borrow Logs" icon="o-archive-box-arrow-down" link="/admin/transactions" />
                        <x-mary-menu-sub title="Users List" icon="o-user">
                        <x-mary-menu-item title="Students" icon="o-academic-cap" link="/admin/students" />
                        <x-mary-menu-sub title="Librarian" icon="o-building-library">
                            <x-mary-menu-item title="Current" icon="o-user" link="/admin/librarians" />
                            <x-mary-menu-item title="Assigning" icon="o-user-plus" link="/admin/librarians/assign" />
                        </x-mary-menu-sub>
                    </x-mary-menu-sub>
                        <x-mary-menu-item title="Attendance" icon="o-user-group" link="/admin/attendance"/>
                        <x-mary-menu-item title="Violation Logs" icon="o-shield-exclamation" link="/admin/violation-logs"/>

                    </x-mary-menu>
                </x-slot:sidebar>
            </div>

            {{-- The `$slot` goes here --}}
            <div class="flex-1 bg-base-100">
                <x-slot:content>
                    {{ $slot }}
                </x-slot:content>
            </div>
        </div>
    </x-mary-main>

    {{-- Toast --}}
    <x-mary-toast />
</body>

</html>
