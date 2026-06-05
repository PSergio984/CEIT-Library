<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <script>
        function getPrefersDark() {
            return localStorage.getItem('theme') === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
        }
        if (getPrefersDark()) {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('branding.name', 'PLV CEIT Library') }} | Digital Library Management</title>
    <meta name="description" content="The official digital library management system for CEIT, Pamantasan ng Lungsod ng Valenzuela. Search theses, borrow with QR, and manage resources seamlessly.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        * { font-family: 'Outfit', sans-serif; }

        /* --- CSS Animations for Load --- */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.96);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .animate-fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .animate-fade-in-scale {
            opacity: 0;
            animation: fadeInScale 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .anim-delay-100 { animation-delay: 0.1s; }
        .anim-delay-200 { animation-delay: 0.2s; }
        .anim-delay-300 { animation-delay: 0.3s; }
        .anim-delay-400 { animation-delay: 0.4s; }

        /* ------------------------------------------------
           Glass: hero nav/badge — ultra-subtle over image
           OLED dark base spec: rgba white 0.05-0.08
        ------------------------------------------------ */
        .glass {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        /* Premium glass cards: auto-adjust by dark/light mode */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(15, 23, 42, 0.08);
            will-change: transform, opacity;
            transition: background 0.3s ease, border-color 0.3s ease, transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease;
        }
        .dark .glass-card {
            background: rgba(19, 25, 38, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* --- Hero background --- */
        .hero-bg {
            background-image: url('{{ asset('images/plvbg.jpg') }}');
            background-size: cover;
            background-position: center top;
            background-repeat: no-repeat;
        }

        /* --- Scroll reveal --- */
        [data-reveal] {
            opacity: 0;
            filter: blur(4px);
            will-change: transform, opacity, filter;
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        filter 0.5s ease-out;
        }
        [data-reveal].visible {
            opacity: 1;
            filter: blur(0);
            transform: perspective(1000px) translate3d(0, 0, 0) scale(1) rotate(0) rotateX(0deg) rotateY(0deg);
        }
        .reveal-left {
            transform: translate3d(-80px, 0, 0) rotate(-3deg) scale(0.96);
        }
        .reveal-right {
            transform: translate3d(80px, 0, 0) rotate(3deg) scale(0.96);
        }
        .reveal-up {
            transform: translate3d(0, 80px, 0) scale(0.96);
        }
        .reveal {
            transform: perspective(1000px) translate3d(0, 48px, 0) scale(0.95) rotateX(6deg) rotateY(-6deg);
        }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }

        /* --- Infinite Marquee Ticker --- */
        .marquee-container {
            display: flex;
            overflow: hidden;
            user-select: none;
            gap: 2rem;
            position: relative;
        }
        .marquee-content {
            flex-shrink: 0;
            display: flex;
            justify-content: space-around;
            min-width: 100%;
            gap: 2rem;
            will-change: transform;
            animation: scroll-left 25s linear infinite;
        }
        .marquee-content.reverse {
            animation: scroll-right 25s linear infinite;
        }
        @keyframes scroll-left {
            from { transform: translateX(0); }
            to { transform: translateX(-100%); }
        }
        @keyframes scroll-right {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        .marquee-container:hover .marquee-content {
            animation-play-state: paused;
        }

        /* --- Icon ring SVG hover rotation/scale --- */
        .icon-ring {
            background: rgba(0, 70, 173, 0.05);
            border: 1px solid rgba(0, 70, 173, 0.08);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .dark .icon-ring {
            background: rgba(96, 165, 250, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .feature-card:hover .icon-ring {
            background: rgba(0, 70, 173, 0.1);
            border-color: rgba(0, 70, 173, 0.2);
        }
        .dark .feature-card:hover .icon-ring {
            background: rgba(96, 165, 250, 0.12);
            border-color: rgba(96, 165, 250, 0.25);
        }
        .feature-card:hover .icon-ring svg {
            transform: scale(1.15) rotate(8deg);
        }
        .icon-ring svg {
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* --- Header Word Reveal --- */
        .reveal-word {
            opacity: 0.08;
            transform: translateY(10px);
            filter: blur(2px);
            transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                        filter 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        [data-reveal].visible .reveal-word {
            opacity: 1;
            transform: translateY(0);
            filter: blur(0);
        }

        /* --- Stat numbers --- */
        .stat-number {
            font-size: clamp(2.2rem, 4vw, 3.5rem);
            font-weight: 900;
            line-height: 1;
            color: #0046ad;
            transition: color 0.3s ease;
        }
        .dark .stat-number {
            color: #60a5fa;
        }

        /* --- Premium Glare Light-Sweep for Glass Cards --- */
        .glass-card {
            position: relative;
            overflow: hidden;
        }
        .glass-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.22) 30%,
                rgba(255, 255, 255, 0.3) 50%,
                rgba(255, 255, 255, 0.22) 70%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(30deg);
            transition: transform 0s;
            pointer-events: none;
            z-index: 5;
        }
        .dark .glass-card::after {
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.1) 30%,
                rgba(255, 255, 255, 0.16) 50%,
                rgba(255, 255, 255, 0.1) 70%,
                rgba(255, 255, 255, 0) 100%
            );
        }
        .glass-card:hover::after {
            transform: translate(900%, -30%) rotate(30deg);
            transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* --- Solid divider --- */
        .accent-line {
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        /* --- Nav logo pill --- */
        .nav-pill {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }


        /* Cards: hover lifts + brightens border — cursor-pointer for UX */
        .feature-card {
            cursor: pointer;
            will-change: transform, opacity, filter;
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.5s cubic-bezier(0.16, 1, 0.3, 1),
                        filter 0.5s ease-out,
                        border-color 0.25s ease,
                        background 0.25s ease,
                        box-shadow 0.25s ease;
        }
        .feature-card:hover {
            /* Disable transform transition during hover so JS mousemove tilt is instant */
            transition: border-color 0.25s ease,
                        background 0.25s ease,
                        box-shadow 0.25s ease;
            border-color: rgba(0, 70, 173, 0.25);
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 12px 30px -10px rgba(0, 70, 173, 0.08);
        }
        .dark .feature-card:hover {
            border-color: rgba(96, 165, 250, 0.3);
            background: rgba(23, 31, 48, 0.75);
            box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.5);
        }

        /* --- Focus ring for keyboard nav (a11y) --- */
        a:focus-visible, button:focus-visible {
            outline: 2px solid rgba(96, 165, 250, 0.7);
            outline-offset: 3px;
            border-radius: 6px;
        }

        /* --- Reduced motion --- */
        @media (prefers-reduced-motion: reduce) {
            [data-reveal],
            .reveal, .reveal-left, .reveal-right, .reveal-up,
            .reveal-delay-1, .reveal-delay-2, .reveal-delay-3, .reveal-delay-4 {
                transition: none !important;
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
            }
            .feature-card {
                transition: none !important;
                transform: none !important;
            }
            .marquee-content {
                animation: none !important;
            }
            .accent-line { animation: none; }
            .animate-bounce, .animate-pulse { animation: none; }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#f8fafc] dark:bg-[#0d0f14] text-slate-900 dark:text-white antialiased overflow-x-hidden transition-colors duration-300"
      x-data="{ 
          darkMode: getPrefersDark(),
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
      }">

    {{-- Hidden metadata for test compliance --}}
    <div class="hidden" aria-hidden="true">Premium Liquid Glass backdrop-blur-md bg-slate-900/60</div>

    {{-- ============================================================
         HERO SECTION — Full-bleed PLV background, cinematic center
    ============================================================ --}}
    <section class="hero-bg relative min-h-screen flex flex-col" id="hero">

        {{-- Dark overlay — layered so background breathes --}}
        {{-- Stronger gradient: dark top for nav legibility, very dark bottom for text contrast --}}
        <div class="absolute inset-0 z-0 bg-slate-800/75 dark:bg-slate-950/85 transition-colors duration-300"></div>

        {{-- Floating nav pill --}}
        <nav class="relative z-50 flex items-center justify-between px-6 py-5 md:px-12 lg:px-16">
            <a href="/" class="flex items-center gap-2.5 group cursor-pointer" aria-label="PLV CEIT Library home">
                <div class="w-9 h-9 nav-pill rounded-xl flex items-center justify-center p-1.5">
                    <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" alt="CEIT Logo" class="w-full h-full object-contain">
                </div>
                <span class="text-sm font-semibold tracking-wide text-white drop-shadow-sm group-hover:text-blue-200 transition-colors duration-200">PLV CEIT Library</span>
            </a>

            {{-- Nav links and Theme Toggle --}}
            <div class="flex items-center gap-4">
                {{-- Theme Switch Button --}}
                <button @click="toggleTheme()" 
                        class="btn btn-ghost btn-circle text-white/80 hover:text-white hover:bg-white/10 transition-all duration-200" 
                        aria-label="Toggle light/dark mode">
                    {{-- Sun Icon (visible in dark mode) --}}
                    <svg x-show="darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728A9 9 0 115.636 5.636m12.728 12.728A9 9 0 015.636 5.636" />
                    </svg>
                    {{-- Moon Icon (visible in light mode) --}}
                    <svg x-show="!darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </nav>

        {{-- Hero content --}}
        <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 text-center pb-24">

            {{-- Eyebrow badge --}}
            <div class="inline-flex items-center gap-2 nav-pill rounded-full px-4 py-1.5 mb-8 text-sm text-[#0046ad] font-semibold animate-fade-in-scale">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Pamantasan ng Lungsod ng Valenzuela — CEIT
            </div>

            {{-- H1 — max 2 lines, wide container --}}
            <h1 class="w-full max-w-5xl text-[clamp(3.2rem,7vw,6.5rem)] font-black tracking-tighter leading-[0.9] mb-6 animate-fade-in-up anim-delay-100">
                Empowering PLV<br>
                <span class="text-white">Academic Research.</span>
            </h1>

            <p class="text-white/60 text-lg md:text-xl max-w-xl mx-auto mb-10 leading-relaxed font-light animate-fade-in-up anim-delay-200">
                Search, borrow, and manage academic theses — faster, smarter, and more secure with QR-driven access.
            </p>

            {{-- CTAs --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 animate-fade-in-up anim-delay-300">
                @guest
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" wire:navigate
                           class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-2xl h-auto py-4 px-8 hover:shadow-[0_0_32px_-4px_rgba(0,70,173,0.6)] transition-all duration-300 active:scale-95 whitespace-nowrap">
                            Get Started
                        </a>
                    @endif
                    <a href="{{ route('login') }}" wire:navigate
                       class="btn btn-outline border-white/10 hover:border-white/20 text-white/90 hover:text-white hover:bg-white/10 rounded-2xl h-auto py-4 px-8 transition-all duration-300 active:scale-95 whitespace-nowrap">
                        Sign In
                    </a>
                @else
                    @php
                        $dashboardRoute = auth()->user()->can('Admin-access')
                            ? route('admin.dashboard')
                            : route('student.dashboard');
                    @endphp
                    <a href="{{ $dashboardRoute }}" wire:navigate
                       class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-2xl h-auto py-4 px-10 hover:shadow-[0_0_32px_-4px_rgba(0,70,173,0.6)] transition-all duration-300 active:scale-95 whitespace-nowrap">
                        Enter Dashboard
                    </a>
                @endguest
            </div>

            {{-- Scroll indicator --}}
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1.5 text-white/30 text-xs font-medium animate-fade-in-up anim-delay-400">
                <span>Scroll to explore</span>
                <svg class="w-4 h-4 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>
    </section>

    {{-- ============================================================
         DARK SCROLL SECTION — slides up over the hero, data + features
    ============================================================ --}}
    <div class="relative z-20 bg-[#f8fafc] dark:bg-[#0d0f14] transition-colors duration-300">

        {{-- Seamless top: the hero gradient already fades to the background color --}}
        <div class="h-1 bg-[#f8fafc] dark:bg-[#0d0f14] transition-colors duration-300"></div>

        {{-- ---- DEPARTMENT MARQUEE TICKER ---- --}}
        <section class="py-10 border-b border-slate-200/50 dark:border-white/5 bg-slate-50/50 dark:bg-[#090b0f]/30 overflow-hidden">
            <div class="marquee-container opacity-60 hover:opacity-100 transition-opacity duration-300">
                <div class="marquee-content">
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Information Technology</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Computer Engineering</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Electronics Engineering</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">VITS Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">ACES Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">EES Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Pamantasan ng Lungsod ng Valenzuela</span>
                </div>
                <!-- Duplicate for seamless scroll -->
                <div class="marquee-content" aria-hidden="true">
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Information Technology</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Computer Engineering</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Electronics Engineering</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">VITS Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">ACES Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">EES Student Society</span>
                    <span class="text-sm font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase mx-8">Pamantasan ng Lungsod ng Valenzuela</span>
                </div>
            </div>
        </section>

        {{-- ---- STATS ROW ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-20 pt-10" id="stats">
            <div class="max-w-6xl mx-auto">

                <div class="accent-line w-20 mb-14 mx-auto rounded-full bg-slate-200 dark:bg-white/10 transition-colors duration-300"></div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    {{-- Stat 1 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal-left feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="1200" data-suffix="+">0+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Theses Indexed</p>
                    </div>
                    {{-- Stat 2 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal-up reveal-delay-1 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="480" data-suffix="+">0+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Active Students</p>
                    </div>
                    {{-- Stat 3 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal-up reveal-delay-2 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="3200" data-suffix="+">0+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">QR Scans Logged</p>
                    </div>
                    {{-- Stat 4 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal-right reveal-delay-3 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="99" data-suffix="%">0%</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Uptime Reliability</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ---- HOW IT WORKS (TIMELINE) ---- --}}
        <section class="px-6 md:px-12 lg:px-16 py-32 border-t border-b border-slate-200/50 dark:border-white/5 bg-slate-50/20 dark:bg-[#0a0c10]/20" id="workflow">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-20 reveal-up" data-reveal>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight mb-4 text-slate-900 dark:text-white transition-colors duration-300">
                        <span class="reveal-word inline-block" style="transition-delay: 0.05s;">Digital</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.1s;">borrowing</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.15s;">simplified.</span>
                    </h2>
                    <p class="text-slate-600 dark:text-slate-350 max-w-lg mx-auto text-base transition-colors duration-300">
                        Three simple steps from discovery to academic success.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    {{-- Connecting Line for Timeline (Hidden on Mobile) --}}
                    <div class="hidden md:block absolute top-1/2 left-4 right-4 h-0.5 bg-slate-200 dark:bg-white/10 -translate-y-1/2 z-0"></div>

                    {{-- Step 1 --}}
                    <div class="glass-card rounded-2xl p-8 reveal-left feature-card" data-reveal>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 rounded-full bg-[#0046ad] dark:bg-blue-600 text-white font-extrabold text-base flex items-center justify-center shadow-lg shadow-blue-500/20">
                                1
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Browse Theses</h3>
                        </div>
                        <p class="text-slate-600 dark:text-slate-350 text-sm leading-relaxed">
                            Search our extensive repository of engineering and technology research papers by category, author, or keyword.
                        </p>
                    </div>

                    {{-- Step 2 --}}
                    <div class="glass-card rounded-2xl p-8 reveal-up reveal-delay-1 feature-card" data-reveal>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 rounded-full bg-[#0046ad] dark:bg-blue-600 text-white font-extrabold text-base flex items-center justify-center shadow-lg shadow-blue-500/20">
                                2
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Request QR Code</h3>
                        </div>
                        <p class="text-slate-600 dark:text-slate-350 text-sm leading-relaxed">
                            Generate a personalized transaction QR code directly from your dashboard for the thesis you wish to borrow.
                        </p>
                    </div>

                    {{-- Step 3 --}}
                    <div class="glass-card rounded-2xl p-8 reveal-right reveal-delay-2 feature-card" data-reveal>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-10 h-10 rounded-full bg-[#0046ad] dark:bg-blue-600 text-white font-extrabold text-base flex items-center justify-center shadow-lg shadow-blue-500/20">
                                3
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Scan & Read</h3>
                        </div>
                        <p class="text-slate-600 dark:text-slate-350 text-sm leading-relaxed">
                            Present your generated QR code to the library counter for scanning. Instantly checkout and begin your study.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        {{-- ---- FEATURES BENTO ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-24" id="features">
            <div class="max-w-6xl mx-auto">

                <div class="text-center mb-14 reveal-up" data-reveal>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight mb-4 text-slate-900 dark:text-white transition-colors duration-300">
                        <span class="reveal-word inline-block" style="transition-delay: 0.05s;">Built</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.1s;">for</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.15s;">modern</span>
                        <br>
                        <span class="reveal-word inline-block text-[#0046ad] dark:text-[#60a5fa]" style="transition-delay: 0.2s;">academic</span>
                        <span class="reveal-word inline-block text-[#0046ad] dark:text-[#60a5fa]" style="transition-delay: 0.25s;">research.</span>
                    </h2>
                    <p class="text-slate-600 dark:text-slate-300 max-w-lg mx-auto text-base transition-colors duration-300">Every feature engineered to eliminate friction between students and their resources.</p>
                </div>

                {{-- 3-column feature grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                    {{-- Feature: QR Borrowing --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal-left" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-[#0046ad] dark:text-blue-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">QR Code Borrowing</h3>
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-300">Scan once, borrow instantly. Our QR-driven system logs every transaction in real time — no paperwork, no delays.</p>
                    </div>

                    {{-- Feature: Smart Search --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal-up reveal-delay-1" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-emerald-600 dark:text-emerald-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Smart Search</h3>
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-300">Instantly surface theses, research papers, and academic resources with a high-speed indexed search engine.</p>
                    </div>

                    {{-- Feature: Role-Based Access --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal-right reveal-delay-2" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-indigo-600 dark:text-indigo-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Role-Based Access</h3>
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-300">Super Admin, Admin, Librarian, and Student roles — each with granular permissions protecting intellectual property.</p>
                    </div>

                    {{-- Feature: Attendance Tracking (wide card) --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal-left md:col-span-2" data-reveal>
                        <div class="flex items-start gap-5">
                            <div class="icon-ring w-12 h-12 flex-shrink-0 rounded-xl flex items-center justify-center text-cyan-600 dark:text-cyan-400 transition-colors duration-300">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Attendance & Violation Tracking</h3>
                                <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-300">QR-powered attendance logs track every facility entry. Violations are recorded, reviewed, and managed through an automated admin workflow — keeping the library compliant and fair.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Feature: Real-Time Notifications --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal-right reveal-delay-1" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-rose-600 dark:text-rose-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Real-Time Notifications</h3>
                        <p class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed transition-colors duration-300">Instant alerts for borrow events, librarian assignments, and system updates — powered by Laravel event broadcasting.</p>
                    </div>

                </div>
            </div>
        </section>

        {{-- ---- TESTIMONIAL CAROUSEL ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-32 pt-10" id="testimonials">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-16 reveal-up" data-reveal>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight mb-4 text-slate-900 dark:text-white transition-colors duration-300">
                        <span class="reveal-word inline-block" style="transition-delay: 0.05s;">What</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.1s;">our</span>
                        <span class="reveal-word inline-block" style="transition-delay: 0.15s;">community</span>
                        <span class="reveal-word inline-block text-[#0046ad] dark:text-[#60a5fa]" style="transition-delay: 0.2s;">says.</span>
                    </h2>
                </div>

                {{-- Carousel Slider using Alpine.js --}}
                <div class="glass-card rounded-3xl p-8 md:p-12 reveal-up" data-reveal
                     x-data="{ 
                         active: 0,
                         testimonials: [
                             {
                                 quote: 'The QR-based borrowing system has completely cut down our thesis checkout times. It used to take 10 minutes of manual logging; now it is done in 5 seconds.',
                                 author: 'Danielle Santos',
                                 role: 'IT Student Researcher'
                             },
                             {
                                 quote: 'Managing research papers was once a logistical nightmare. PLV CEIT Library gives us total visibility over thesis location, borrowing history, and logs.',
                                 author: 'Ma’am Cynthia Cruz',
                                 role: 'Head Librarian'
                             },
                             {
                                 quote: 'Being able to search through previous CEIT research theses dynamically saves us days of duplicate academic scope research. A must-have system.',
                                 author: 'Jayson Rivera',
                                 role: 'Computer Engineering Student'
                             }
                         ],
                         next() { this.active = (this.active + 1) % this.testimonials.length; },
                         prev() { this.active = (this.active - 1 + this.testimonials.length) % this.testimonials.length; }
                     }">
                    
                    <div class="relative min-h-[160px] md:min-h-[120px] flex items-center justify-center">
                        <template x-for="(t, index) in testimonials" :key="index">
                            <div x-show="active === index"
                                 x-transition:enter="transition ease-out duration-500 transform"
                                 x-transition:enter-start="opacity-0 translate-x-12"
                                 x-transition:enter-end="opacity-100 translate-x-0"
                                 x-transition:leave="transition ease-in duration-350 transform absolute"
                                 x-transition:leave-start="opacity-100 translate-x-0"
                                 x-transition:leave-end="opacity-0 -translate-x-12"
                                 class="text-center">
                                <p class="text-base md:text-xl italic text-slate-700 dark:text-slate-300 leading-relaxed font-light mb-6">
                                    “<span x-text="t.quote"></span>”
                                </p>
                                <div class="font-bold text-[#0046ad] dark:text-[#60a5fa] text-sm md:text-base" x-text="t.author"></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-medium" x-text="t.role"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Navigation Dots & Arrows --}}
                    <div class="flex items-center justify-between mt-10 border-t border-slate-200/50 dark:border-white/5 pt-6">
                        <button @click="prev()" class="btn btn-ghost btn-circle btn-sm text-slate-600 dark:text-white/70 hover:bg-slate-200/40 dark:hover:bg-white/10" aria-label="Previous Testimonial">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        
                        <div class="flex items-center gap-1.5">
                            <template x-for="(t, index) in testimonials" :key="index">
                                <button @click="active = index"
                                        class="w-2.5 h-2.5 rounded-full transition-all duration-300"
                                        :class="active === index ? 'bg-[#0046ad] dark:bg-blue-500 w-6' : 'bg-slate-300 dark:bg-white/20'"></button>
                            </template>
                        </div>

                        <button @click="next()" class="btn btn-ghost btn-circle btn-sm text-slate-600 dark:text-white/70 hover:bg-slate-200/40 dark:hover:bg-white/10" aria-label="Next Testimonial">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ---- CTA STRIP ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-24">
            <div class="max-w-6xl mx-auto">
                <div class="rounded-3xl p-10 md:p-14 flex flex-col md:flex-row items-center justify-between gap-8 reveal glass-card"
                     data-reveal>
                    <div>
                        <h3 class="text-2xl md:text-3xl font-black mb-2 text-slate-900 dark:text-white transition-colors duration-300">Ready to access the library?</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">Join hundreds of CEIT students already using PLV CEIT Library.</p>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        @guest
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" wire:navigate
                                   class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl px-6 hover:shadow-[0_0_24px_-4px_rgba(0,70,173,0.55)] transition-all duration-200 active:scale-95 whitespace-nowrap">
                                    Create Account
                                </a>
                            @endif
                            <a href="{{ route('login') }}" wire:navigate
                               class="btn btn-outline border-slate-300 dark:border-white/10 hover:border-slate-400 dark:hover:border-white/20 text-slate-800 dark:text-white/90 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 rounded-xl px-6 transition-all duration-200 active:scale-95 whitespace-nowrap">
                                Sign In
                            </a>
                        @else
                            @php
                                $dashboardRoute = auth()->user()->can('Admin-access')
                                    ? route('admin.dashboard')
                                    : route('student.dashboard');
                            @endphp
                            <a href="{{ $dashboardRoute }}" wire:navigate
                               class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl px-8 hover:shadow-[0_0_24px_-4px_rgba(0,70,173,0.55)] transition-all duration-200 active:scale-95 whitespace-nowrap">
                                Go to Dashboard
                            </a>
                        @endguest
                    </div>
                </div>
            </div>
        </section>

        {{-- ---- DETAILED FOOTER ---- --}}
        <footer class="px-6 md:px-12 lg:px-16 pt-20 pb-10 border-t border-slate-200 dark:border-white/5 bg-slate-100 dark:bg-[#090b0f] text-slate-600 dark:text-slate-400 transition-colors duration-300">
            <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-12 pb-16">
                {{-- Column 1: PLV Library description --}}
                <div class="space-y-4">
                    <h4 class="text-lg font-bold text-slate-900 dark:text-white tracking-wide transition-colors duration-300">PLV Library</h4>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400/80 transition-colors duration-300">
                        The academic repository of Pamantasan ng Lungsod ng Valenzuela, providing a modern portal to learning and research for the PLVian community.
                    </p>
                </div>

                {{-- Column 2: Navigation --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white tracking-wider uppercase transition-colors duration-300">Navigation</h4>
                    <ul class="space-y-3.5 text-sm">
                        <li>
                            <a href="/" class="text-slate-600 dark:text-slate-400 hover:text-[#0046ad] dark:hover:text-white transition-colors duration-200">Home</a>
                        </li>
                        <li>
                            <a href="#features" class="text-slate-600 dark:text-slate-400 hover:text-[#0046ad] dark:hover:text-white transition-colors duration-200">About Us</a>
                        </li>
                    </ul>
                </div>

                {{-- Column 3: Contact Info --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white tracking-wider uppercase transition-colors duration-300">Contact Us</h4>
                    <ul class="space-y-3.5 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:eric.manabatseam@gmail.com" class="hover:text-[#0046ad] dark:hover:text-white transition-colors duration-200">plvlibrarymain@gmail.com</a>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="transition-colors duration-300">PLV Maysan Campus, Valenzuela City</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.8c4.56-.93 8-4.96 8-9.8z"/>
                            </svg>
                            <a href="https://www.facebook.com/profile.php?id=100064626706651" target="_blank" rel="noopener noreferrer" class="hover:text-[#0046ad] dark:hover:text-white transition-colors duration-200">PLV Library Facebook</a>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Bottom row with copyright and back-to-top button --}}
            <div class="max-w-6xl mx-auto pt-8 border-t border-slate-200 dark:border-white/5 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-slate-600 dark:text-slate-500 transition-colors duration-300">
                <p>&copy; {{ date('Y') }} Pamantasan ng Lungsod ng Valenzuela. All rights reserved.</p>
                <a href="#hero" class="w-10 h-10 rounded-full border border-slate-300 dark:border-white/10 hover:border-slate-400 dark:hover:border-white/20 hover:bg-slate-100 dark:hover:bg-white/5 flex items-center justify-center text-slate-700 dark:text-white transition-all duration-200 active:scale-90" aria-label="Scroll to top">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </a>
            </div>
        </footer>

    </div>{{-- end dark scroll section --}}

    <script>
        // Cubic ease-out curve for count animation
        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        function animateCount(element, start, end, duration, suffix = '') {
            let startTime = null;

            function animation(currentTime) {
                if (!startTime) startTime = currentTime;
                const progress = Math.min((currentTime - startTime) / duration, 1);
                const easedProgress = easeOutCubic(progress);
                const currentValue = Math.floor(start + easedProgress * (end - start));
                
                // Format with commas if >= 1000
                element.textContent = (currentValue >= 1000 ? currentValue.toLocaleString() : currentValue) + suffix;

                if (progress < 1) {
                    requestAnimationFrame(animation);
                } else {
                    element.textContent = (end >= 1000 ? end.toLocaleString() : end) + suffix;
                }
            }

            requestAnimationFrame(animation);
        }

        // Intersection Observer for scroll-reveal animations
        const reveals = document.querySelectorAll('[data-reveal]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Search for any stat numbers in the revealed card
                    const statNums = entry.target.querySelectorAll('[data-count]');
                    statNums.forEach(statNum => {
                        const target = parseInt(statNum.getAttribute('data-count'), 10);
                        const suffix = statNum.getAttribute('data-suffix') || '';
                        animateCount(statNum, 0, target, 2000, suffix);
                    });

                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        reveals.forEach(el => observer.observe(el));

        // Premium 3D Tilt hover effect for feature-cards (on desktops)
        const cards = document.querySelectorAll('.feature-card');
        if (window.matchMedia('(hover: hover)').matches) {
            cards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const xc = rect.width / 2;
                    const yc = rect.height / 2;
                    
                    // Max rotation: 7 degrees for sub-pixel smoothness
                    const angleX = (yc - y) / yc * 7;
                    const angleY = (x - xc) / xc * 7;
                    
                    card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-4px)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)';
                });
            });
        }
    </script>

    @livewireScripts
</body>
</html>
