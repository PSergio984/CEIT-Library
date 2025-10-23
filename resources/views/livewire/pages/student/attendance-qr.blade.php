<div class="p-4 sm:p-8 bg-base-100 shadow-lg sm:rounded-lg text-base-content">
    {{-- Attendance QR Component --}}
    <div class="max-w-xl">
        {{-- Header with Icon --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="bg-primary/10 p-3 rounded-full">
                    <x-mary-icon name="o-qr-code" class="w-8 h-8 text-primary"/>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-base-content">Your Attendance QR Code</h2>
                    <p class="text-sm text-base-content/70">Show this to the librarian for check-in/check-out</p>
                </div>
            </div>
        </div>

        {{-- QR Code Display with Enhanced Styling --}}
        <div class="relative bg-gradient-to-br from-base-100 to-base-200 p-6 sm:p-8 rounded-2xl shadow-2xl mb-6 border-2 border-primary/20 w-full flex justify-center">
            {{-- Corner decorations --}}
            <div class="absolute top-2 left-2 w-8 h-8 border-t-4 border-l-4 border-primary rounded-tl-lg"></div>
            <div class="absolute top-2 right-2 w-8 h-8 border-t-4 border-r-4 border-primary rounded-tr-lg"></div>
            <div class="absolute bottom-2 left-2 w-8 h-8 border-b-4 border-l-4 border-primary rounded-bl-lg"></div>
            <div class="absolute bottom-2 right-2 w-8 h-8 border-b-4 border-r-4 border-primary rounded-br-lg"></div>
            
            {{-- QR Code with white background and padding --}}
            <div class="bg-white p-6 rounded-xl shadow-inner">
             <img src="{{ $this->qrCodeDataUri }}" 
                 alt="Student attendance QR code for {{ $user->first_name ?? 'Unknown' }} {{ $user->last_name ?? '' }}. Scan to record attendance at the library."
                 class="w-64 h-64"
                 style="image-rendering: pixelated;"/>
            </div>
            
            {{-- Valid badge --}}
            <div class="absolute -bottom-3 left-1/2 transform -translate-x-1/2">
                <div class="badge badge-success gap-1 shadow-lg px-4 py-3">
                    <x-mary-icon name="o-check-circle" class="w-4 h-4"/>
                    @if($qrValidUntil)
                        @php
                            $tz = Auth::user()->timezone ?? config('app.timezone');
                        @endphp
                        Valid until {{ \Carbon\Carbon::parse($qrValidUntil)->setTimezone($tz)->format('M d, Y g:i A') }} ({{ $tz }})
                    @else
                        Valid for 24 hours
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Active Session Status --}}
        @if($activeSessionId)
            <div class="alert alert-info w-full mb-4">
                <x-mary-icon name="o-clock" class="w-5 h-5"/>
                <div class="text-sm">
                    <p class="font-semibold">Active Session</p>
                    <p>You have an ongoing library session. This QR code is linked to your current visit.</p>
                </div>
            </div>
        @endif

        {{-- User Info Card --}}
        @php $user = Auth::user(); @endphp
        @if($user)
            <div class="bg-base-200 rounded-lg p-4 mb-4 w-full">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Name:</span>
                        <span class="font-semibold">{{ $user->first_name ?? '-' }} {{ $user->last_name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Email:</span>
                        <span class="font-semibold">{{ $user->email ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/70">Status:</span>
                        <span class="badge badge-{{ $user->status === 'active' ? 'success' : 'error' }} badge-sm">
                            {{ ucfirst($user->status) }}
                        </span>
                    </div>
                </div>
            </div>
        {{-- @else unreachable: user should always be present if authenticated --}}
        @endif

        {{-- Instructions with Step Cards --}}
        <div class="w-full mb-6">
            <h3 class="text-lg font-semibold mb-3 flex items-center gap-2">
                <x-mary-icon name="o-information-circle" class="w-5 h-5 text-info"/>
                How to Use
            </h3>
            <div class="grid gap-3">
                <div class="flex items-start gap-3 bg-base-200 p-3 rounded-lg">
                    <div class="badge badge-info badge-lg">1</div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Show to Librarian</p>
                        <p class="text-xs text-base-content/70">Present this QR code at the library desk</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 bg-base-200 p-3 rounded-lg">
                    <div class="badge badge-success badge-lg">2</div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Check-In Scan</p>
                        <p class="text-xs text-base-content/70">Librarian scans for entry recording</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 bg-base-200 p-3 rounded-lg">
                    <div class="badge badge-warning badge-lg">3</div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">Check-Out Scan</p>
                        <p class="text-xs text-base-content/70">Scan again when leaving to complete session</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Security Notice --}}
        <div class="alert alert-warning w-full mb-4">
            <x-mary-icon name="o-shield-check" class="w-5 h-5"/>
            <div class="text-xs">
                <p class="font-semibold">Security Notice:</p>
                <p>
                    This QR code is encrypted and may be downloaded for your personal use. Store it securely and <span class="font-bold">do not share</span> it publicly or with unauthorized individuals. Only share your QR code directly with authorized librarians or staff for attendance purposes.
                </p>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row gap-3 w-full mb-4">
            <button wire:click="downloadQrCode"
                    wire:loading.attr="disabled"
                    wire:target="downloadQrCode"
                    wire:loading.attr="aria-busy"
                    class="btn btn-primary flex-1 gap-2 shadow-lg">
                <span wire:loading.remove wire:target="downloadQrCode" class="flex items-center gap-2">
                    <x-mary-icon name="o-arrow-down-tray" class="w-5 h-5"/>
                    Download QR Code
                </span>
                <span wire:loading wire:target="downloadQrCode" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm text-primary"></span>
                    Preparing download...
                </span>
            </button>
            <button onclick="window.print()"
                    class="btn btn-outline btn-primary flex-1 gap-2">
                <x-mary-icon name="o-printer" class="w-5 h-5"/>
                Print
            </button>
        </div>
        
        {{-- Refresh QR Button --}}
        <div class="w-full mb-6">
            <button wire:click="refreshQrCode"
                    wire:loading.attr="disabled"
                    wire:target="refreshQrCode"
                    wire:loading.attr="aria-busy"
                    class="btn btn-outline btn-warning w-full gap-2">
                <span wire:loading.remove wire:target="refreshQrCode" class="flex items-center gap-2">
                    <x-mary-icon name="o-arrow-path" class="w-5 h-5"/>
                    Refresh QR Code
                </span>
                <span wire:loading wire:target="refreshQrCode" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm text-warning"></span>
                    Refreshing...
                </span>
            </button>
            <p class="text-xs text-center text-base-content/60 mt-2">
                Click to generate a new QR code if needed
            </p>
        </div>
        
        {{-- Helpful Tips --}}
        <div class="w-full">
            <div class="bg-base-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <x-mary-icon name="o-light-bulb" class="w-5 h-5 text-warning flex-shrink-0 mt-0.5"/>
                    <div class="text-xs text-base-content/70 space-y-1">
                        <p><strong>Tip:</strong> Download or screenshot this QR code for offline use</p>
                        <p><strong>Note:</strong> Valid for 24 hours. Regenerates each visit for security.</p>
                        <p><strong>Reminder:</strong> Keep your QR code private and secure</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
