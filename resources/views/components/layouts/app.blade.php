<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/ceit-logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- PWA & Push Notifications -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0046ad">
    <link rel="apple-touch-icon" href="{{ Vite::asset('resources/images/ceit-logo.png') }}">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => {
                        console.log('Service Worker registered successfully.', reg);
                        @auth
                            requestPushPermission(reg);
                        @endauth

                        // Check if there is already a waiting worker on page load
                        if (reg.waiting) {
                            window.pwaWaitingWorker = reg.waiting;
                            window.dispatchEvent(new CustomEvent('pwa-update-available'));
                        }

                        // Listen for future updates
                        reg.onupdatefound = () => {
                            const newWorker = reg.installing;
                            if (newWorker) {
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        window.pwaWaitingWorker = newWorker;
                                        window.dispatchEvent(new CustomEvent('pwa-update-available'));
                                    }
                                });
                            }
                        };
                    })
                    .catch(err => console.error('Service Worker registration failed:', err));
            });

            // Reload page when the active service worker changes (after skipWaiting)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });
        }

        async function requestPushPermission(reg) {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    console.log('Notification permission denied.');
                    return;
                }

                const response = await fetch('/api/push/vapid-key');
                const data = await response.json();
                if (!data.publicKey) {
                    console.log('No public key provided.');
                    return;
                }

                const subscribeOptions = {
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(data.publicKey)
                };

                const subscription = await reg.pushManager.subscribe(subscribeOptions);
                
                await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(subscription)
                });
                console.log('User is subscribed to Push Notifications.');
            } catch (err) {
                console.error('Failed to subscribe user to Push:', err);
            }
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later.
            window.deferredPrompt = e;
            // Update UI notify the user they can install the PWA
            window.dispatchEvent(new CustomEvent('pwa-install-available'));
        });

        window.addEventListener('appinstalled', (e) => {
            // Hide the app-provided install promotion
            window.deferredPrompt = null;
            window.dispatchEvent(new CustomEvent('pwa-install-hidden'));
            console.log('PWA was installed');
        });

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    </script>

    @livewireStyles
</head>

<body class="min-h-screen font-sans antialiased bg-background text-foreground flex flex-col">

    {{-- NAVBAR mobile only --}}
    <x-mary-nav sticky class="lg:hidden">
        <x-slot:brand>
            <label for="main-drawer" class="btn btn-square btn-ghost lg:hidden mr-2">
                <x-mary-icon name="o-bars-3" class="w-6 h-6" />
            </label>
            <div class="font-bold text-primary">CEIT Library</div>
        </x-slot:brand>
        <x-slot:actions>
            {{-- Mobile User Dropdown --}}
            <livewire:layout.user-menu />
        </x-slot:actions>
    </x-mary-nav>
    {{-- MAIN --}}
    <x-mary-main full-width class="flex-1 flex flex-col">
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <div class="flex items-center justify-center py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <a href="/dashboard">
                            <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" class="h-10 w-10" alt="CEIT Logo" loading="lazy">
                        </a>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class=" transition-all duration-300" x-show="!collapsed">
                            <div class="font-bold text-xl text-base-content whitespace-nowrap">CEIT Library</div>
                        </div>
                        {{-- Theme Toggle beside Library text --}}
                        <div x-show="!collapsed">
                            <x-mary-theme-toggle class="btn btn-sm btn-circle btn-ghost" />
                        </div>
                    </div>
                </div>
            </div>


            {{-- MENU --}}
            <x-mary-menu activate-by-route class="[&_.mary-menu-sub]:!pl-0 [&_.mary-menu-item]:!pl-0" wire:transition>
                <x-mary-menu-item title="Dashboard" tooltip="Dashboard" icon="o-home" link="/dashboard" wire:navigate.hover />
                <x-mary-menu-item title="Academic Papers" tooltip="Academic Papers" icon="o-book-open" link="/academic-papers" wire:navigate.hover />
                <x-mary-menu-item title="Rules & Regulations" tooltip="Rules & Regulations" icon="o-clipboard-document-list"
                    link="/rule-and-regulation" wire:navigate.hover />
                <x-mary-menu-item title="Profile" tooltip="Profile" icon="o-user" link="/profile" wire:navigate.hover />
                <x-mary-menu-item title="Transactions" tooltip="Transactions" icon="o-archive-box" link="/transactions" wire:navigate.hover />
                <x-mary-menu-item title="Credit Score History" tooltip="Credit Score History" icon="o-chart-bar" link="/credit-score-history" wire:navigate.hover />
                
                {{-- Notifications with Badge --}}
                <x-mary-menu-item title="Notifications" tooltip="Notifications" icon="o-bell" link="/notifications" wire:navigate.hover>
                    @if(auth()->check() && auth()->user()->unreadNotifications()->count() > 0)
                        <x-slot:actions>
                            <span class="badge badge-primary badge-sm">{{ auth()->user()->unreadNotifications()->count() }}</span>
                        </x-slot:actions>
                    @endif
                </x-mary-menu-item>
                
                @can('access-admin-dashboard')
                    <x-mary-menu-item title="Admin Dashboard" tooltip="Admin Dashboard" icon="o-squares-2x2" link="/admin/dashboard" wire:navigate.hover />
                @endcan

            </x-mary-menu>



        </x-slot:sidebar>

        {{-- CONTENT --}}
        <x-slot:content>
            <div class="flex flex-col min-h-screen pb-16 lg:pb-0">
                {{-- Desktop Navigation --}}
                <div class="hidden lg:block">
                    <livewire:layout.navigation />
                </div>

                <div class="flex-1 p-5 lg:px-10 lg:py-8">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <footer class="bg-background border-t border-border mt-auto w-full">
                    <div class="max-w-7xl mx-auto py-6 px-5 lg:px-10">
                        <div class="text-center text-sm text-muted-foreground">
                            <p>&copy; {{ date('Y') }} PLV eLib - CEIT Library Management System</p>
                            <p class="mt-1">Pamantasan ng Lungsod ng Valenzuela</p>
                        </div>
                    </div>
                </footer>
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- BOTTOM NAVIGATION (Mobile only) --}}
    <div id="mobile-nav" class="lg:hidden fixed bottom-0 left-0 right-0 z-[9999] bg-base-100 border-t border-base-300 h-16 shadow-2xl">
        <div class="flex justify-around items-center h-full">
            <a href="/dashboard" class="flex flex-col items-center justify-center w-full h-full {{ request()->is('dashboard*') || request()->is('student/dashboard*') ? 'text-primary' : 'text-base-content/70' }}">
                <x-mary-icon name="o-home" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">Home</span>
            </a>
            <a href="/academic-papers" class="flex flex-col items-center justify-center w-full h-full {{ request()->is('academic-papers*') ? 'text-primary' : 'text-base-content/70' }}">
                <x-mary-icon name="o-book-open" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">Papers</span>
            </a>
            
            {{-- Scanner link - Hidden for regular students if not in dev, but plan asks for it --}}
            @can('librarian-or-admin-access')
            <a href="/test-qr" class="flex flex-col items-center justify-center w-full h-full">
                <div class="bg-primary text-primary-content p-3 rounded-full -mt-8 shadow-lg border-4 border-base-100">
                    <x-mary-icon name="o-qr-code" class="w-7 h-7" />
                </div>
                <span class="text-[10px] mt-1 font-bold text-primary">Scan</span>
            </a>
            @endcan

            <a href="/notifications" class="flex flex-col items-center justify-center w-full h-full {{ request()->is('notifications*') ? 'text-primary' : 'text-base-content/70' }}">
                <div class="relative">
                    <x-mary-icon name="o-bell" class="w-6 h-6" />
                    @if(auth()->check() && auth()->user()->unreadNotifications()->count() > 0)
                        <span class="badge badge-primary badge-xs absolute -top-1 -right-1"></span>
                    @endif
                </div>
                <span class="text-[10px] mt-1 font-medium">Alerts</span>
            </a>
            <label for="main-drawer" class="flex flex-col items-center justify-center w-full h-full text-base-content/70 cursor-pointer">
                <x-mary-icon name="o-ellipsis-horizontal" class="w-6 h-6" />
                <span class="text-[10px] mt-1 font-medium">More</span>
            </label>
        </div>
    </div>

    {{-- Toast --}}
    <x-mary-toast />
</body>

</html>
