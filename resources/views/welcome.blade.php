<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Welcome</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen font-sans relative"
    style="background-image: url('{{ asset('images/plvbg.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <!-- Dark Blue Overlay for opacity effect -->
    <div class="absolute inset-0 bg-slate-800/70 z-0"></div>

    <!-- Header -->
    <header class="flex justify-between items-center px-4 sm:px-6 md:px-8 lg:px-10 py-3 sm:py-4 z-20 relative"
        style="background-color: #273F4F;">
        <a href="/"
            class="flex items-center text-white text-lg sm:text-xl md:text-2xl font-bold hover:opacity-80 transition">
            <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 flex-shrink-0">
                <img src="{{ asset('images/ceit-logo.png') }}" alt="CEIT Logo" class="w-full h-full object-contain">
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
    <main
        class="flex items-center justify-center min-h-[70vh] text-center relative z-20 my-8 sm:my-12 md:my-16 lg:my-20 px-4 sm:px-6 md:px-8">
        <div
            class="bg-white/50 p-4 sm:p-6 md:p-8 lg:p-12 rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-xs sm:max-w-md md:max-w-2xl lg:max-w-4xl xl:max-w-7xl flex flex-col items-center justify-center mx-auto">
            <h1
                class="text-gray-800 text-xl sm:text-2xl md:text-3xl lg:text-4xl xl:text-5xl font-bold mb-4 sm:mb-6 md:mb-8 text-center leading-tight">
                CEIT Library Management System
            </h1>
            <div
                class="mx-auto mb-4 sm:mb-6 md:mb-8 w-32 h-32 sm:w-40 sm:h-40 md:w-48 md:h-48 lg:w-64 lg:h-64 xl:w-72 xl:h-72 flex items-center justify-center">
                <img src="{{ asset('images/ceit-logo.png') }}" alt="CEIT Logo"
                    class="w-full h-full object-contain drop-shadow-xl mx-auto">
            </div>
            <p
                class="text-gray-700 text-sm sm:text-base md:text-lg lg:text-xl xl:text-2xl mb-6 sm:mb-8 md:mb-10 lg:mb-12 font-medium leading-relaxed text-center px-2 sm:px-4 md:px-6">
                PLV eLib is a digital library system that makes searching<br class="hidden md:block">
                <span class="md:hidden"> </span>and borrowing theses faster, easier, and more secure.
            </p>
            @guest
                <div
                    class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center w-full max-w-md sm:max-w-none">
                    <a href="{{ route('register') }}" wire:navigate
                        class="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-4 sm:py-3 sm:px-6 md:py-4 md:px-8 rounded-lg text-sm sm:text-base md:text-lg transition duration-300 min-w-[120px] sm:min-w-[140px] md:min-w-[150px] flex items-center justify-center gap-2 w-full sm:w-auto">
                        REGISTER
                    </a>
                    <a href="{{ route('login') }}" wire:navigate
                        class="border-2 border-slate-700 text-slate-700 hover:bg-slate-700 hover:text-white font-bold py-2 px-4 sm:py-3 sm:px-6 md:py-4 md:px-8 rounded-lg text-sm sm:text-base md:text-lg transition duration-300 min-w-[120px] sm:min-w-[140px] md:min-w-[150px] flex items-center justify-center gap-2 w-full sm:w-auto">
                        LOGIN
                    </a>
                </div>
            @endguest
            @auth
                <div class="flex justify-center items-center w-full max-w-md sm:max-w-none">
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-4 sm:py-3 sm:px-6 md:py-4 md:px-8 rounded-lg text-sm sm:text-base md:text-lg transition duration-300 min-w-[120px] sm:min-w-[140px] md:min-w-[150px] flex items-center justify-center gap-2 w-full sm:w-auto">
                        GO TO DASHBOARD
                    </a>
                </div>
            @endauth
        </div>
    </main>

    @livewireScripts
</body>

</html>
