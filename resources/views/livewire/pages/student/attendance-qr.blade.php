<div>
    {{-- Attendance QR Component --}}
    <div class="flex flex-col items-center justify-center py-4 sm:py-6">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6 text-center">
            <h2 class="text-xl sm:text-2xl font-bold text-base-content mb-2">Your Attendance QR Code</h2>
            <p class="text-sm sm:text-base text-base-content/70">Show this to the librarian for library check-in/check-out</p>
        </div>

        {{-- QR Code Display --}}
        <div class="bg-white p-4 sm:p-6 rounded-xl shadow-lg mb-4 sm:mb-6">
            <img src="{{ $this->qrCodeDataUri }}" 
                 alt="Attendance QR Code" 
                 class="w-64 h-64 sm:w-80 sm:h-80 rounded-lg"/>
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

        {{-- Instructions --}}
        <div class="alert alert-info max-w-md mb-4">
            <x-mary-icon name="o-information-circle" class="w-5 h-5"/>
            <div class="text-sm">
                <p class="font-semibold mb-1">How to use:</p>
                <ol class="list-decimal list-inside space-y-1 text-xs">
                    <li>Show this QR code to the librarian</li>
                    <li>They will scan it for check-in</li>
                    <li>Scan again when leaving for check-out</li>
                    <li>QR code is valid for 24 hours</li>
                </ol>
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

        {{-- Download Button --}}
        <button wire:click="downloadQrCode"
                class="btn btn-primary gap-2">
            <x-mary-icon name="o-arrow-down-tray" class="w-5 h-5"/>
            Download QR Code
        </button>
        
        {{-- Refresh Notice --}}
        <p class="text-xs text-base-content/50 mt-4 text-center">
            Note: QR code regenerates each time you open this page for enhanced security
        </p>
    </div>
</div>
