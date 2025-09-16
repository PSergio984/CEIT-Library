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
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-base-200 flex flex-col">
            <livewire:layout.navigation />
            <x-mary-toast />
           <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-base-100 shadow">
                    <div class="max-w-7xl mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-8">
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-semibold text-base-content">
                            {{ $header }}
                        </h1>
                    </div>
                </header>
            @endif


            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <!-- Simple Footer -->
            <footer class="bg-base-100 border-t border-base-300">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-sm text-base-content">
                        <p>&copy; {{ date('Y') }} PLV eLib - CEIT Library Management System</p>
                        <p class="mt-1">Pamantasan ng Lungsod ng Valenzuela</p>
                    </div>
                </div>
            </footer>
        </div>

        <!-- Mary UI Toast Component -->
        <x-mary-toast />
    </body>
</html>
