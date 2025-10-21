<div>
    {{-- Attendance QR Component --}}
    <div class="flex flex-col items-center justify-center py-4 sm:py-6 px-4">
        {{-- Header with Icon --}}
        <div class="mb-4 sm:mb-6 text-center">
            <div class="flex justify-center mb-3">
                <div class="bg-primary/10 p-4 rounded-full">
                    <x-mary-icon name="o-qr-code" class="w-12 h-12 text-primary"/>
                </div>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold text-base-content mb-2">Your Attendance QR Code</h2>
            <p class="text-sm sm:text-base text-base-content/70">Show this to the librarian for check-in/check-out</p>
        </div>

        {{-- QR Code Display with Enhanced Styling --}}
        <div class="relative bg-gradient-to-br from-base-100 to-base-200 p-6 sm:p-8 rounded-2xl shadow-2xl mb-6 border-2 border-primary/20">
            {{-- Corner decorations --}}
            <div class="absolute top-2 left-2 w-8 h-8 border-t-4 border-l-4 border-primary rounded-tl-lg"></div>
            <div class="absolute top-2 right-2 w-8 h-8 border-t-4 border-r-4 border-primary rounded-tr-lg"></div>
            <div class="absolute bottom-2 left-2 w-8 h-8 border-b-4 border-l-4 border-primary rounded-bl-lg"></div>
            <div class="absolute bottom-2 right-2 w-8 h-8 border-b-4 border-r-4 border-primary rounded-br-lg"></div>
            
            {{-- QR Code with white background and padding --}}
            <div class="bg-white p-6 rounded-xl shadow-inner">
                <img src="{{ $this->qrCodeDataUri }}" 
                     alt="Attendance QR Code" 
                     class="w-64 h-64 sm:w-80 sm:h-80 mx-auto"
                     style="image-rendering: pixelated;"/>
            </div>
            
            {{-- Valid badge --}}
            <div class="absolute -bottom-3 left-1/2 transform -translate-x-1/2">
                <div class="badge badge-success gap-1 shadow-lg px-4 py-3">
                    <x-mary-icon name="o-check-circle" class="w-4 h-4"/>
                    Valid for 24 hours. Regenerates each visit for security.
                </div>
            </div>
        </div>

        {{-- User Info Card --}}
        @php $user = Auth::user(); @endphp
        @if($user)
            <div class="bg-base-200 rounded-lg p-4 mb-4 w-full max-w-md">
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
        @else
            <div class="bg-base-200 rounded-lg p-4 mb-4 w-full max-w-md">
                <div class="space-y-2 text-sm text-center">
                    <span class="text-base-content/70">User information unavailable.</span>
                </div>
            </div>
        @endif

        {{-- Instructions with Step Cards --}}
        <div class="w-full max-w-md mb-6">
            <h3 class="text-lg font-semibold mb-3 text-center flex items-center justify-center gap-2">
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
        <div class="alert alert-warning max-w-md mb-4">
            <x-mary-icon name="o-shield-check" class="w-5 h-5"/>
            <div class="text-xs">
                <p class="font-semibold">Security Notice:</p>
                <p>
                    This QR code is encrypted and may be downloaded for your personal use. Store it securely and <span class="font-bold">do not share</span> it publicly or with unauthorized individuals. Only share your QR code directly with authorized librarians or staff for attendance purposes.
                </p>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row gap-3 w-full max-w-md mb-6">
            <button wire:click="downloadQrCode"
                    class="btn btn-primary flex-1 gap-2 shadow-lg">
                <x-mary-icon name="o-arrow-down-tray" class="w-5 h-5"/>
                Download QR Code
            </button>
            <button onclick="window.print()"
                    class="btn btn-outline btn-primary flex-1 gap-2">
                <x-mary-icon name="o-printer" class="w-5 h-5"/>
                Print
            </button>
        </div>
        
        {{-- Helpful Tips --}}
        <div class="w-full max-w-md">
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
