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
    </head>
    <body class="min-h-screen bg-cover bg-center font-sans relative" style="background-image: url('{{ asset('images/plvbg.jpg') }}');">
        <!-- Blue Overlay for opacity effect -->
        <div class="absolute inset-0 bg-[#273F4F]/80 z-0"></div>
        <!-- Header (fixed at the top, like welcome.blade.php) -->
        <header class="flex justify-between items-center px-10 py-4 z-30 relative w-full" style="background-color: #273F4F;">
            <a href="/" class="flex items-center text-white text-2xl font-bold hover:opacity-80 transition">
                <div class="w-12 h-12">
                    <img src="{{ asset('images/ceit-logo.png') }}" alt="CEIT Logo">
                </div>
                <span class="ml-2">CEIT Library</span>
            </a>
            <div>
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>
        <div class="flex items-center justify-center min-h-screen relative z-10">
            <div class="relative w-full max-w-lg mx-auto">
                <!-- Card background layer for dark header effect -->
                <div class="absolute inset-0 bg-[#273F4F] rounded-t-2xl" style="height: 110px; z-index: 0;"></div>
                <div class="relative z-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
        <style>
            /* Remove floating elements and overlays from previous design */
        </style>
    </body>
</html>
