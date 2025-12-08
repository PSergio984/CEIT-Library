<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="ceit">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/ceit-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans relative" style="background-image: url('{{ Vite::asset('resources/images/plvbg.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <!-- Dark Blue Overlay for opacity effect -->
        <div class="absolute inset-0 bg-slate-800/70 z-0"></div>

   <!-- Header -->
        <header class="flex justify-between items-center px-4 sm:px-6 md:px-8 lg:px-10 py-3 sm:py-4 z-20 relative" style="background-color: #273F4F;">
            <a href="/" class="flex items-center text-white text-lg sm:text-xl md:text-2xl font-bold hover:opacity-80 transition">
                <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 flex-shrink-0">
                    <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo" class="w-full h-full object-contain">
                </div>
                <span class="ml-1 sm:ml-2 hidden sm:inline">CEIT Library</span>
                <span class="ml-1 sm:ml-2 sm:hidden">CEIT</span>
            </a>
            <div class="flex items-center space-x-2 sm:space-x-4 md:space-x-6">
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex items-center justify-center min-h-[calc(100vh-80px)] sm:min-h-[calc(100vh-88px)] md:min-h-[calc(100vh-96px)] text-center relative z-20 py-4 sm:py-6 md:py-8 lg:py-12 px-4 sm:px-6 md:px-8">
            <div class="bg-white/50 p-4 sm:p-6 md:p-8 lg:p-12 xl:p-16 rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-xs sm:max-w-sm md:max-w-lg lg:max-w-2xl xl:max-w-4xl min-h-[400px] sm:min-h-[450px] md:min-h-[500px] lg:min-h-[550px] flex flex-col items-center justify-center mx-auto">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
