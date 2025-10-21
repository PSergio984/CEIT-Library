<div class="container mx-auto p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">QR Code System Test</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Generate Test QR --}}
            <div class="card bg-base-200 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">1. Generate Test QR Code</h2>
                    <p class="text-sm text-base-content/70">Click to generate a test QR code with current user data</p>
                    
                    <button wire:click="generateTestQr" class="btn btn-primary mt-4">
                        <x-mary-icon name="o-qr-code" class="w-5 h-5"/>
                        Generate Test QR
                    </button>
                    
                    @if($testQrCode)
                        <div class="mt-4 bg-white p-4 rounded-lg">
                            <img src="{{ $testQrCode }}" alt="Test QR" class="w-full max-w-xs mx-auto">
                        </div>
                        <div class="mt-2 text-xs font-mono bg-base-300 p-2 rounded break-all">
                            {{ $testQrData }}
                        </div>
                    @endif
                </div>
            </div>
            
            {{-- Scan QR --}}
            <div class="card bg-base-200 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">2. Test Scanner</h2>
                    <p class="text-sm text-base-content/70">Open scanner and scan the generated QR code</p>
                    
                    <button wire:click="openScanner" class="btn btn-success mt-4">
                        <x-mary-icon name="o-camera" class="w-5 h-5"/>
                        Open Scanner
                    </button>
                    
                    @if($lastScanResult)
                        <div class="mt-4">
                            <div class="alert alert-success">
                                <x-mary-icon name="o-check-circle" class="w-5 h-5"/>
                                <span>Scan successful!</span>
                            </div>
                            <div class="mt-2 text-xs font-mono bg-base-300 p-2 rounded break-all">
                                {{ $lastScanResult }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Validation Results --}}
        @if($validationResult)
            <div class="card bg-base-200 shadow-xl mt-6">
                <div class="card-body">
                    <h2 class="card-title">3. Validation Result</h2>
                    <div class="overflow-x-auto">
                        <pre class="text-xs bg-base-300 p-4 rounded">{!! json_encode($validationResult, JSON_PRETTY_PRINT) !!}</pre>                    </div>
                </div>
            </div>
        @endif
        
        {{-- Instructions --}}
        <div class="card bg-base-100 shadow-xl mt-6">
            <div class="card-body">
                <h2 class="card-title">Testing Instructions</h2>
                <ol class="list-decimal list-inside space-y-2 text-sm">
                    <li>Click "Generate Test QR" to create a QR code with your user data</li>
                    <li>Click "Open Scanner" to activate the camera scanner</li>
                    <li>Point your camera at the generated QR code on screen (or print it)</li>
                    <li>The scanner should automatically detect and decode the QR</li>
                    <li>Check the validation result to ensure data is correct</li>
                </ol>
                
                <div class="alert alert-info mt-4">
                    <x-mary-icon name="o-information-circle" class="w-5 h-5"/>
                    <div class="text-sm">
                        <p><strong>Tip:</strong> For best results, display the QR code on another device or print it. Scanning from the same screen may not work well.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Include QR Scanner Component --}}
    <livewire:qr-scanner />
</div>
