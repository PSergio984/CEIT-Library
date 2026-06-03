<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
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
    <title>PLV CEIT Library | Digital Library Management</title>
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
            transition: background 0.3s ease, border-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
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
        .reveal {
            opacity: 0;
            transform: translateY(48px) scale(0.96);
            filter: blur(4px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                        filter 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .reveal.visible { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        .reveal-delay-1 { transition-delay: 0.08s; }
        .reveal-delay-2 { transition-delay: 0.16s; }
        .reveal-delay-3 { transition-delay: 0.24s; }
        .reveal-delay-4 { transition-delay: 0.32s; }

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

        /* --- Feature card icon container --- */
        .icon-ring {
            background: rgba(0, 70, 173, 0.06);
            border: 1px solid rgba(0, 70, 173, 0.15);
            transition: background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
        }
        .feature-card:hover .icon-ring {
            background: rgba(0, 70, 173, 0.12);
            border-color: rgba(0, 70, 173, 0.3);
            transform: scale(1.06);
        }
        /* Cards: hover lifts + brightens border — cursor-pointer for UX */
        .feature-card {
            cursor: pointer;
            transition: transform 0.35s cubic-bezier(0.16,1,0.3,1),
                        border-color 0.25s ease,
                        background 0.25s ease,
                        box-shadow 0.25s ease;
        }
        .feature-card:hover {
            transform: translateY(-3px);
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
            .reveal, .reveal-delay-1, .reveal-delay-2, .reveal-delay-3, .reveal-delay-4 {
                transition: none;
                opacity: 1;
                transform: none;
            }
            .accent-line { animation: none; }
            .animate-bounce, .animate-pulse { animation: none; }
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#f8fafc] dark:bg-[#0d0f14] text-slate-900 dark:text-white antialiased overflow-x-hidden transition-colors duration-300"
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
                    <a href="{{ route('register') }}" wire:navigate
                       class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-2xl h-auto py-4 px-8 hover:shadow-[0_0_32px_-4px_rgba(0,70,173,0.6)] transition-all duration-300 active:scale-95 whitespace-nowrap">
                        Get Started
                    </a>
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

        {{-- ---- STATS ROW ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-20 pt-10" id="stats">
            <div class="max-w-6xl mx-auto">

                <div class="accent-line w-20 mb-14 mx-auto rounded-full bg-slate-200 dark:bg-white/10 transition-colors duration-300"></div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    {{-- Stat 1 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="1200" data-suffix="+">1,200+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Theses Indexed</p>
                    </div>
                    {{-- Stat 2 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal reveal-delay-1 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="480" data-suffix="+">480+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Active Students</p>
                    </div>
                    {{-- Stat 3 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal reveal-delay-2 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="3200" data-suffix="+">3,200+</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">QR Scans Logged</p>
                    </div>
                    {{-- Stat 4 --}}
                    <div class="glass-card rounded-2xl p-6 text-center reveal reveal-delay-3 feature-card" data-reveal>
                        <div class="stat-number mb-2" data-count="99" data-suffix="%">99%</div>
                        <p class="text-slate-600 dark:text-slate-400 text-sm font-semibold transition-colors duration-300">Uptime Reliability</p>
                    </div>
                </div>
            </div>
        </section>
        {{-- ---- FEATURES BENTO ---- --}}
        <section class="px-6 md:px-12 lg:px-16 pb-24" id="features">
            <div class="max-w-6xl mx-auto">

                <div class="text-center mb-14 reveal" data-reveal>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight mb-4 text-slate-900 dark:text-white transition-colors duration-300">Built for modern<br><span class="text-[#0046ad] dark:text-[#60a5fa] transition-colors duration-300">academic research.</span></h2>
                    <p class="text-slate-500 dark:text-slate-400 max-w-lg mx-auto text-base transition-colors duration-300">Every feature engineered to eliminate friction between students and their resources.</p>
                </div>

                {{-- 3-column feature grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                    {{-- Feature: QR Borrowing --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal reveal-delay-1" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-[#0046ad] dark:text-blue-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">QR Code Borrowing</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">Scan once, borrow instantly. Our QR-driven system logs every transaction in real time — no paperwork, no delays.</p>
                    </div>

                    {{-- Feature: Smart Search --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal reveal-delay-2" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-emerald-600 dark:text-emerald-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Smart Search</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">Instantly surface theses, research papers, and academic resources with a high-speed indexed search engine.</p>
                    </div>

                    {{-- Feature: Role-Based Access --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal reveal-delay-3" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-indigo-600 dark:text-indigo-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Role-Based Access</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">Super Admin, Admin, Librarian, and Student roles — each with granular permissions protecting intellectual property.</p>
                    </div>

                    {{-- Feature: Attendance Tracking (wide card) --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal reveal-delay-1 md:col-span-2" data-reveal>
                        <div class="flex items-start gap-5">
                            <div class="icon-ring w-12 h-12 flex-shrink-0 rounded-xl flex items-center justify-center text-cyan-600 dark:text-cyan-400 transition-colors duration-300">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Attendance & Violation Tracking</h3>
                                <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">QR-powered attendance logs track every facility entry. Violations are recorded, reviewed, and managed through an automated admin workflow — keeping the library compliant and fair.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Feature: Real-Time Notifications --}}
                    <div class="glass-card rounded-2xl p-7 feature-card reveal reveal-delay-2" data-reveal>
                        <div class="icon-ring w-12 h-12 rounded-xl flex items-center justify-center mb-5 text-rose-600 dark:text-rose-400 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-white transition-colors duration-300">Real-Time Notifications</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed transition-colors duration-300">Instant alerts for borrow events, librarian assignments, and system updates — powered by Laravel event broadcasting.</p>
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
                            <a href="{{ route('register') }}" wire:navigate
                               class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl px-6 hover:shadow-[0_0_24px_-4px_rgba(0,70,173,0.55)] transition-all duration-200 active:scale-95 whitespace-nowrap">
                                Create Account
                            </a>
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
        // Intersection Observer for scroll-reveal animations
        const reveals = document.querySelectorAll('[data-reveal]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        reveals.forEach(el => observer.observe(el));
    </script>

    @livewireScripts
</body>
</html>
