<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="ceit">
    <head>
        {{-- Anti-flash: apply theme class before paint (same as welcome.blade.php) --}}
        <script>
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
            }
        </script>

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
    <body
        class="relative min-h-screen bg-white bg-cover bg-center bg-no-repeat font-sans"
        style="background-image: url('{{ asset('images/plvbg.jpg') }}');"
        x-data="{
            darkMode: document.documentElement.classList.contains('dark'),
            toggleTheme() {
                this.darkMode = !this.darkMode;
                if (this.darkMode) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            }
        }"
    >
        <div class="absolute inset-0 bg-slate-800/70 dark:bg-slate-950/85 z-0 transition-colors duration-300"></div>

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
                {{-- Theme toggle — same system as welcome.blade.php (Alpine.js + localStorage + dark class) --}}
                <button @click="toggleTheme()"
                        class="btn btn-ghost btn-circle text-white/80 hover:text-white hover:bg-white/10 transition-all duration-200"
                        aria-label="Toggle light/dark mode">
                    {{-- Sun Icon — visible in dark mode --}}
                    <svg x-show="darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728A9 9 0 115.636 5.636m12.728 12.728A9 9 0 015.636 5.636" />
                    </svg>
                    {{-- Moon Icon — visible in light mode --}}
                    <svg x-show="!darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex items-center justify-center min-h-[calc(100vh-80px)] sm:min-h-[calc(100vh-88px)] md:min-h-[calc(100vh-96px)] text-center relative z-20 py-4 sm:py-6 md:py-8 lg:py-12 px-4 sm:px-6 md:px-8">
            <div class="bg-white/50 dark:bg-slate-900/60 backdrop-blur-sm p-4 sm:p-6 md:p-8 lg:p-12 xl:p-16 rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-xs sm:max-w-sm md:max-w-lg lg:max-w-2xl xl:max-w-4xl min-h-[400px] sm:min-h-[450px] md:min-h-[500px] lg:min-h-[550px] flex flex-col items-center justify-center mx-auto transition-colors duration-300">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>

