<nav class="flex items-center gap-3 justify-end">
    @auth
        <a href="{{ auth()->user()->can('Admin-access') ? route('admin.dashboard') : route('student.dashboard') }}" wire:navigate
            class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl px-5 hover:shadow-[0_0_16px_-3px_rgba(0,70,173,0.5)] active:scale-95 whitespace-nowrap">
            Dashboard
        </a>
    @endauth

    @guest
        <a href="{{ route('login') }}" wire:navigate
            class="btn btn-outline border-white/10 hover:border-white/20 text-white/90 hover:text-white hover:bg-white/10 rounded-xl px-5 transition-all duration-200 active:scale-95 whitespace-nowrap">
            Log in
        </a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" wire:navigate
                class="btn border-none bg-[#0046ad] hover:bg-[#003da0] text-white font-bold rounded-xl px-5 hover:shadow-[0_0_16px_-3px_rgba(0,70,173,0.5)] active:scale-95 whitespace-nowrap">
                Register
            </a>
        @endif
    @endguest
</nav>


