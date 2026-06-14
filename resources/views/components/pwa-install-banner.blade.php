<div 
    x-data="{ 
        show: false, 
        deferredPrompt: null,
        init() {
            window.addEventListener('beforeinstallprompt', (e) => {
                // Prevent the mini-infobar from appearing on mobile
                e.preventDefault();
                // Stash the event so it can be triggered later.
                this.deferredPrompt = e;
                // Update UI notify the user they can install the PWA
                this.show = true;
                console.log('PWA Install prompt deferred');
            });

            window.addEventListener('appinstalled', (e) => {
                // Clear the deferredPrompt so it can be garbage collected
                this.deferredPrompt = null;
                this.show = false;
                console.log('PWA was installed');
            });
        },
        async install() {
            if (!this.deferredPrompt) return;
            
            // Show the install prompt
            this.deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            const { outcome } = await this.deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            
            // We've used the prompt, and can't use it again, throw it away
            this.deferredPrompt = null;
            this.show = false;
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-full"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-full"
    class="fixed bottom-20 left-4 right-4 z-[60] lg:left-auto lg:right-8 lg:bottom-8 lg:max-w-sm"
    style="display: none;"
>
    <div class="bg-base-100 border border-primary/20 shadow-2xl rounded-2xl p-5 flex flex-col gap-4 relative overflow-hidden group">
        {{-- Decorative background --}}
        <div class="absolute -top-12 -right-12 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors"></div>
        
        <div class="flex items-start gap-4">
            <div class="bg-primary/10 p-3 rounded-xl flex-shrink-0">
                <img src="{{ Vite::asset('resources/images/ceit-logo.png') }}" class="w-10 h-10 object-contain" alt="CEIT Logo">
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg leading-tight">Install CEIT Lib</h3>
                <p class="text-sm text-base-content/70 mt-1">Get the best experience by installing our app on your home screen.</p>
            </div>
            <button @click="show = false" class="btn btn-sm btn-circle btn-ghost -mr-2 -mt-2">
                <x-mary-icon name="o-x-mark" class="w-4 h-4" />
            </button>
        </div>

        <div class="flex gap-2">
            <button @click="install()" class="btn btn-primary flex-1 shadow-lg shadow-primary/20">
                <x-mary-icon name="o-arrow-down-tray" class="w-4 h-4" />
                Install Now
            </button>
            <button @click="show = false" class="btn btn-ghost flex-1">Maybe Later</button>
        </div>
    </div>
</div>
