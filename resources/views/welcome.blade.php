<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PLV CEIT Library | Premium Liquid Glass</title>

    <!-- Fonts: DM Sans as fallback for General Sans/Satoshi -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'DM Sans', sans-serif;
        }
        .glass-panel {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .parallax-bg {
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-50 antialiased overflow-x-hidden">
    <!-- Parallax Background Layer -->
    <div class="fixed inset-0 z-0 parallax-bg" style="background-image: url('{{ asset('images/plvbg.jpg') }}');"></div>
    <div class="fixed inset-0 z-1 bg-slate-950/40 backdrop-brightness-50"></div>

    <!-- Navigation -->
    <nav class="relative z-50 flex items-center justify-between px-6 py-8 md:px-12">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-slate-900/60 backdrop-blur-md rounded-xl flex items-center justify-center p-2 border border-white/10">
                <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo" class="w-full h-full object-contain">
            </div>
            <span class="text-xl md:text-2xl font-bold tracking-tight">PLV CEIT Library</span>
        </div>
        
        <div class="flex items-center gap-4">
            @if (Route::has('login'))
                <livewire:welcome.navigation />
            @endif
        </div>
    </nav>

    <!-- Hero Section -->
    <main class="relative z-10 min-h-[90vh] flex flex-col items-center justify-center px-6 text-center">
        <div x-data="{ show: false }" x-init="nextTick(() => show = true)" class="max-w-5xl">
            <h2 x-show="show" 
                x-transition:enter="transition ease-out duration-1000"
                x-transition:enter-start="opacity-0 -translate-y-10"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="text-teal-400 font-medium tracking-[0.2em] uppercase text-sm md:text-base mb-6" x-cloak>
                Premium Experience
            </h2>
            
            <h1 x-show="show"
                x-transition:enter="transition ease-out duration-1000 delay-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="text-5xl md:text-8xl lg:text-9xl font-bold tracking-tighter leading-[0.9] mb-8" x-cloak>
                PLV CEIT <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-teal-400">Library</span>
            </h1>

            <p x-show="show"
               x-transition:enter="transition ease-out duration-1000 delay-500"
               x-transition:enter-start="opacity-0 translate-y-10"
               x-transition:enter-end="opacity-100 translate-y-0"
               class="text-slate-300 text-lg md:text-2xl max-w-2xl mx-auto mb-12 leading-relaxed" x-cloak>
                A high-conversion digital sanctuary for CEIT researchers. Seamless thesis management wrapped in a premium, translucent interface.
            </p>

            <div x-show="show"
                 x-transition:enter="transition ease-out duration-1000 delay-700"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="flex flex-col sm:flex-row items-center justify-center gap-6" x-cloak>
                @guest
                    <a href="{{ route('register') }}" wire:navigate class="group relative px-8 py-4 bg-white text-slate-950 font-bold rounded-2xl transition-all duration-300 hover:scale-105 active:scale-95 overflow-hidden">
                        <span class="relative z-10">Get Started</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-teal-400 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </a>
                    <a href="{{ route('login') }}" wire:navigate class="px-8 py-4 glass-panel text-white font-bold rounded-2xl transition-all duration-300 hover:bg-white/10 active:scale-95 border border-white/20">
                        Sign In
                    </a>
                @else
                    @php
                        $dashboardRoute = auth()->user()->can('Admin-access') 
                            ? route('admin.dashboard') 
                            : route('student.dashboard');
                    @endphp
                    <a href="{{ $dashboardRoute }}" wire:navigate class="px-12 py-4 bg-gradient-to-r from-blue-500 to-teal-500 text-white font-bold rounded-2xl transition-all duration-300 hover:shadow-[0_0_30px_-5px_rgba(59,130,246,0.5)] active:scale-95">
                        Enter Dashboard
                    </a>
                @endguest
            </div>
        </div>
    </main>

    <!-- Features Section -->
    <section class="relative z-10 py-32 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div x-data="{ shown: false }" x-intersect.once="shown = true" 
                     class="glass-panel p-8 rounded-3xl group hover:border-teal-500/50 transition-colors duration-500">
                    <div x-show="shown" 
                         x-transition:enter="transition ease-out duration-1000"
                         x-transition:enter-start="opacity-0 translate-y-20"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <div class="w-14 h-14 bg-blue-500/20 rounded-2xl flex items-center justify-center mb-6 text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Smart Search</h3>
                        <p class="text-slate-400 leading-relaxed">Instantly locate any thesis or research paper with our high-speed indexing engine.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div x-data="{ shown: false }" x-intersect.once="shown = true" 
                     class="glass-panel p-8 rounded-3xl group hover:border-teal-500/50 transition-colors duration-500">
                    <div x-show="shown" 
                         x-transition:enter="transition ease-out duration-1000 delay-200"
                         x-transition:enter-start="opacity-0 translate-y-20"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <div class="w-14 h-14 bg-teal-500/20 rounded-2xl flex items-center justify-center mb-6 text-teal-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">QR Integration</h3>
                        <p class="text-slate-400 leading-relaxed">Borrow and return resources effortlessly using secure QR code technology.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div x-data="{ shown: false }" x-intersect.once="shown = true" 
                     class="glass-panel p-8 rounded-3xl group hover:border-teal-500/50 transition-colors duration-500">
                    <div x-show="shown" 
                         x-transition:enter="transition ease-out duration-1000 delay-400"
                         x-transition:enter-start="opacity-0 translate-y-20"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <div class="w-14 h-14 bg-indigo-500/20 rounded-2xl flex items-center justify-center mb-6 text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Secure Access</h3>
                        <p class="text-slate-400 leading-relaxed">Built with enterprise-grade security to protect academic intellectual property.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="relative z-10 py-12 text-center text-slate-500 text-sm">
        <p>&copy; {{ date('Y') }} Pamantasan ng Lungsod ng Valenzuela - CEIT. All rights reserved.</p>
    </footer>

    @livewireScripts
</body>
</html>
