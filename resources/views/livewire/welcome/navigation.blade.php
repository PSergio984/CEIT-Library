<nav class="-mx-3 flex flex-1 justify-end">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white"
        >
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white flex items-center gap-1 sm:gap-2"
        >
            <x-gmdi-login-r class="w-3 h-3 sm:w-4 sm:h-4"/>
            <span class="hidden xs:inline">Log in</span>
            <span class="xs:hidden">Login</span>
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="rounded-md px-2 py-2 sm:px-3 text-white text-sm sm:text-base ring-1 ring-transparent transition hover:text-white/80 focus:outline-none focus-visible:ring-white flex items-center gap-1 sm:gap-2"
            >
                <x-feathericon-user-plus class="w-3 h-3 sm:w-4 sm:h-4"/>
                <span class="hidden xs:inline">Register</span>
                <span class="xs:hidden">Register</span>
            </a>
        @endif
    @endauth
</nav>
