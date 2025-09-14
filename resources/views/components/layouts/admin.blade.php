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
    <body class="min-h-screen font-sans antialiased bg-base-200 ">

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
                <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

                    {{-- BRAND --}}
                    <div class="ml-5 pt-5 flex items-center justify-between">
                        <div>test</div>
                        <x-mary-theme-toggle class="btn btn-circle btn-ghost btn-xs pr-3" />
                    </div>


                    {{-- MENU --}}
                    <x-mary-menu activate-by-route>

                        {{-- User --}}
                        @if($user = auth()->user())
                            <x-mary-menu-separator />

                            <x-mary-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                                <x-slot:actions>
                                    <x-mary-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff" no-wire-navigate link="/logout" />
                                </x-slot:actions>
                            </x-mary-list-item>

                            <x-mary-menu-separator />
                        @endif

                        <x-mary-menu-item title="Thesis List" icon="o-book-open" link="/admin/thesis" />
                        <x-mary-menu-item title="User List" icon="o-user" link="/testlink"/>

                    </x-mary-menu>
                </x-slot:sidebar>
            </div>

            {{-- The `$slot` goes here --}}
            <div class="flex-1">
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
