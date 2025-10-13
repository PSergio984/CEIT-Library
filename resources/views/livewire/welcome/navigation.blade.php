<nav class="-mx-3 flex flex-1 justify-end">
    @auth
        <a href="{{ url('/dashboard') }}" wire:navigate
            class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white">
            Dashboard
        </a>
    @endauth

    @guest
        <a href="{{ route('login') }}" wire:navigate
            class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white flex items-center gap-1 sm:gap-2">

            <span class="hidden sm:inline">Log in</span>
            <span class="sm:hidden">Login</span>
        </a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" wire:navigate
                class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white flex items-center gap-1 sm:gap-2">
                <span class="hidden sm:inline">Register</span>
                <span class="sm:hidden">Register</span>
            </a>
        @endif
    @endguest
</nav>
