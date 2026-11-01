<div
    x-data="{
        mode: null,
        deferredPrompt: null,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches;
            const installDismissed = localStorage.getItem('pwa-prompt-dismissed');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                if (!installDismissed) {
                    this.mode = 'install';
                }
            });

            window.addEventListener('appinstalled', (e) => {
                this.deferredPrompt = null;
                this.considerPush();
            });

            if (standalone || installDismissed) {
                this.considerPush();
            }
        },
        considerPush() {
            if ('Notification' in window
                && Notification.permission === 'default'
                && !localStorage.getItem('pwa-push-dismissed')) {
                this.mode = 'push';
            } else {
                this.mode = null;
            }
        },
        async install() {
            if (!this.deferredPrompt) return;

            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;

            this.deferredPrompt = null;
            this.mode = null;
        },
        async enablePush() {
            if (window.enablePushNotifications) {
                await window.enablePushNotifications();
            }
            this.mode = null;
        },
        dismiss() {
            if (this.mode === 'install') {
                localStorage.setItem('pwa-prompt-dismissed', 'true');
            } else if (this.mode === 'push') {
                localStorage.setItem('pwa-push-dismissed', 'true');
            }
            this.mode = null;
        }
    }"
    x-show="mode"
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
                <img x-show="mode === 'install'" src="{{ Vite::asset('resources/images/ceit-logo.png') }}" class="w-10 h-10 object-contain" alt="CEIT Logo">
                <x-mary-icon x-show="mode === 'push'" name="o-bell" class="w-10 h-10 text-primary" />
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg leading-tight" x-text="mode === 'install' ? 'Install CEIT Lib' : 'Enable Notifications'"></h3>
                <p class="text-sm text-base-content/70 mt-1" x-text="mode === 'install' ? 'Get the best experience by installing our app on your home screen.' : 'Get due-date and bag reminders on this device.'"></p>
            </div>
            <button @click="dismiss()" class="btn btn-sm btn-circle btn-ghost -mr-2 -mt-2">
                <x-mary-icon name="o-x-mark" class="w-4 h-4" />
            </button>
        </div>

        <div class="flex gap-2">
            <button x-show="mode === 'install'" @click="install()" class="btn btn-primary flex-1 shadow-lg shadow-primary/20">
                <x-mary-icon name="o-arrow-down-tray" class="w-4 h-4" />
                Install Now
            </button>
            <button x-show="mode === 'push'" @click="enablePush()" class="btn btn-primary flex-1 shadow-lg shadow-primary/20">
                <x-mary-icon name="o-bell" class="w-4 h-4" />
                Enable
            </button>
            <button @click="dismiss()" class="btn btn-ghost flex-1">Maybe Later</button>
        </div>
    </div>
</div>
