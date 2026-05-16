<div x-data="{ scanning: @entangle('isScanning') }" x-init="$watch('scanning', value => {
    console.log('Scanning state changed to:', value);
    if (!value) {
        console.log('Modal closing, stopping camera');
        if (window.forceStopScanner) {
            window.forceStopScanner();
        }
    }
})">
    @if ($isScanning)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm"
            x-data="{
                showDebug: false,
                scanMode: null,
                selectMode(mode) {
                    this.scanMode = mode;
                    console.log('Scan mode selected:', mode);
                    if (mode === 'camera') {
                        setTimeout(() => {
                            if (window.initCameraScanner) {
                                window.initCameraScanner();
                            }
                        }, 100);
                    } else if (mode === 'file') {
                        setTimeout(() => {
                            if (window.initFileUploadScanner) {
                                window.initFileUploadScanner();
                            }
                        }, 100);
                    }
                },
                reset() {
                    this.scanMode = null;
                    if (window.forceStopScanner) {
                        window.forceStopScanner();
                    }
                }
            }">
            <div class="bg-base-100 rounded-2xl shadow-2xl p-4 sm:p-6 mx-4 max-h-[95vh] overflow-y-auto"
                :class="scanMode === null ? 'max-w-sm' : 'max-w-full sm:max-w-4xl w-full'"
                :style="scanMode === null ? 'max-width: 24rem;' : 'max-width: calc(100vw - 2rem);'">
                {{-- Header --}}
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 p-2 rounded-lg">
                            <x-mary-icon name="o-qr-code" class="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">
                                <span x-show="scanMode === null">Choose Scan Method</span>
                                <span x-show="scanMode === 'camera'">Camera Scanner</span>
                                <span x-show="scanMode === 'file'">File Upload Scanner</span>
                            </h3>
                            <p class="text-xs text-base-content/60">
                                <span x-show="scanMode === null">Select how you want to scan</span>
                                <span x-show="scanMode === 'camera'">Position QR code in camera view</span>
                                <span x-show="scanMode === 'file'">Upload a QR code image</span>
                            </p>
                        </div>
                    </div>
                    <button wire:click="stopScanning" class="btn btn-sm btn-circle btn-ghost">
                        <x-mary-icon name="o-x-mark" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Mode Selection --}}
                <div x-show="scanMode === null" class="space-y-4">
                    {{-- Info Banner --}}
                    <div class="alert alert-info mb-4">
                        <x-mary-icon name="o-information-circle" class="w-5 h-5 flex-shrink-0" />
                        <div class="text-sm">
                            Upload an image containing the QR code or use your camera to scan directly.
                        </div>
                    </div>

                    {{-- Camera Option --}}
                    <button @click="selectMode('camera')"
                        class="w-full btn btn-lg btn-primary gap-4 h-auto py-5 hover:scale-[1.02] transition-transform">
                        <div class="bg-primary-content/20 p-3 rounded-lg flex-shrink-0">
                            <x-mary-icon name="o-camera" class="w-6 h-6" />
                        </div>
                        <div class="flex-1 text-left">
                            <div class="font-bold text-base">Use Camera</div>
                            <div class="text-xs opacity-80 font-normal mt-1">Scan QR code with your device camera</div>
                        </div>
                        <x-mary-icon name="o-chevron-right" class="w-5 h-5 opacity-60 flex-shrink-0" />
                    </button>

                    {{-- Divider --}}
                    <div class="flex items-center gap-4 my-4">
                        <div class="flex-1 border-t border-base-300"></div>
                        <span class="text-xs text-base-content/50 font-medium">OR</span>
                        <div class="flex-1 border-t border-base-300"></div>
                    </div>

                    {{-- File Upload Option --}}
                    <button @click="selectMode('file')"
                        class="w-full btn btn-lg btn-outline btn-secondary gap-4 h-auto py-5 hover:scale-[1.02] transition-transform">
                        <div class="bg-secondary-content/20 p-3 rounded-lg flex-shrink-0">
                            <x-mary-icon name="o-photo" class="w-6 h-6" />
                        </div>
                        <div class="flex-1 text-left">
                            <div class="font-bold text-base">Upload Image</div>
                            <div class="text-xs opacity-80 font-normal mt-1">Select a QR code image from your device</div>
                        </div>
                        <x-mary-icon name="o-chevron-right" class="w-5 h-5 opacity-60 flex-shrink-0" />
                    </button>
                </div>

                {{-- Camera Scanner Mode --}}
                <div x-show="scanMode === 'camera'" x-transition>
                    <button @click="reset()" class="btn btn-sm btn-ghost mb-4">
                        <x-mary-icon name="o-arrow-left" class="w-4 h-4" />
                        Back to selection
                    </button>

                    {{-- Scanner Status --}}
                    <div class="mb-4">
                        <div class="flex items-center justify-between bg-base-200 rounded-lg p-3">
                            <div class="flex items-center gap-2">
                                <span class="loading loading-ring loading-sm text-success"></span>
                                <span class="text-sm font-medium">Camera Active</span>
                            </div>
                            <button @click="showDebug = !showDebug" class="btn btn-xs btn-ghost">
                                <x-mary-icon name="o-information-circle" class="w-4 h-4" />
                                Debug
                            </button>
                        </div>

                        {{-- Debug Panel --}}
                        <div x-show="showDebug" x-transition class="mt-2 bg-base-300 rounded-lg p-3 text-xs font-mono">
                            <div id="debug-info" class="space-y-1">
                                <p>Waiting for scanner initialization...</p>
                            </div>
                        </div>
                    </div>

                    {{-- QR Scanner Container with Enhanced Frame --}}
                    <div class="relative mb-4 flex justify-center">
                        {{-- Decorative scanning frame --}}
                        <div class="absolute inset-0 pointer-events-none z-10 flex items-center justify-center">
                            <div class="relative w-full h-full flex items-center justify-center">
                                <div
                                    class="absolute top-2 left-2 sm:top-4 sm:left-4 w-12 h-12 sm:w-16 sm:h-16 border-t-4 border-l-4 border-primary rounded-tl-lg">
                                </div>
                                <div
                                    class="absolute top-2 right-2 sm:top-4 sm:right-4 w-12 h-12 sm:w-16 sm:h-16 border-t-4 border-r-4 border-primary rounded-tr-lg">
                                </div>
                                <div
                                    class="absolute bottom-2 left-2 sm:bottom-4 sm:left-4 w-12 h-12 sm:w-16 sm:h-16 border-b-4 border-l-4 border-primary rounded-bl-lg">
                                </div>
                                <div
                                    class="absolute bottom-2 right-2 sm:bottom-4 sm:right-4 w-12 h-12 sm:w-16 sm:h-16 border-b-4 border-r-4 border-primary rounded-br-lg">
                                </div>
                            </div>
                        </div>

                        <div id="qr-reader"
                            class="w-full rounded-xl overflow-hidden bg-gray-900 shadow-inner aspect-square sm:aspect-video"
                            style="min-height: 300px; max-height: min(80vh, 600px); width: 100%;"></div>
                    </div>

                    {{-- Instructions --}}
                    <div class="bg-info/10 border border-info/20 rounded-lg p-4">
                        <div class="flex gap-3">
                            <x-mary-icon name="o-information-circle" class="w-5 h-5 text-info flex-shrink-0" />
                            <div class="text-sm space-y-2">
                                <p class="font-semibold text-info">Scanning Tips:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs text-base-content/70">
                                    <li>Hold QR code steady within the frame</li>
                                    <li>Ensure good lighting on the QR code</li>
                                    <li>Keep QR code flat and avoid glare</li>
                                    <li>Try moving closer or farther if not scanning</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- File Upload Mode --}}
                <div x-show="scanMode === 'file'" x-transition>
                    <button @click="reset()" class="btn btn-sm btn-ghost mb-4">
                        <x-mary-icon name="o-arrow-left" class="w-4 h-4" />
                        Back to selection
                    </button>

                    <div class="border-2 border-dashed border-primary/30 rounded-xl p-4 sm:p-8 bg-base-200/50 mb-4">
                        <div id="file-qr-reader"
                            class="w-full rounded-xl overflow-hidden bg-gray-900 aspect-square sm:aspect-video"
                            style="min-height: 300px; max-height: 60vh;"></div>
                    </div>

                    <div class="bg-base-200 border border-base-300 rounded-lg p-4 mb-4">
                        <label class="label">
                            <span class="label-text font-semibold flex items-center gap-2">
                                <x-mary-icon name="o-photo" class="w-5 h-5 text-primary" />
                                Select QR Code Image:
                            </span>
                        </label>
                        <input type="file" id="qr-input-file" accept="image/*"
                            class="file-input file-input-bordered file-input-primary w-full" />
                        <p class="text-xs text-base-content/60 mt-2">Supports PNG, JPG, and other image formats</p>
                    </div>

                    {{-- Instructions --}}
                    <div class="bg-info/10 border border-info/20 rounded-lg p-4">
                        <div class="flex gap-3">
                            <x-mary-icon name="o-information-circle" class="w-5 h-5 text-info flex-shrink-0" />
                            <div class="text-sm space-y-2">
                                <p class="font-semibold text-info">Upload Tips:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs text-base-content/70">
                                    <li>Upload a clear image of the QR code</li>
                                    <li>Ensure the QR code is not blurry or damaged</li>
                                    <li>The entire QR code should be visible in the image</li>
                                    <li>Avoid images with heavy shadows or reflections</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @script
            <script>
                // Unified QR Scanner Script using Html5Qrcode
                let html5QrCode = null;
                let isInitialized = false;
                let isInitializing = false;
                let scanCount = 0;

                console.log('QR Scanner Overhaul: Unified script loaded');

                // Debug logging helper
                function updateDebugInfo(message, isError = false) {
                    const debugDiv = document.getElementById('debug-info');
                    if (debugDiv) {
                        const timestamp = new Date().toLocaleTimeString();
                        const className = isError ? 'text-error' : 'text-success';
                        const p = document.createElement('p');
                        p.className = className;
                        p.textContent = `[${timestamp}] ${message}`;
                        debugDiv.appendChild(p);
                        debugDiv.scrollTop = debugDiv.scrollHeight;
                    }
                    console.log(`[QR Scanner] ${message}`);
                }

                /**
                 * Initialize camera scanner using Html5Qrcode
                 */
                window.initCameraScanner = async function() {
                    console.log('initCameraScanner called');

                    if (isInitializing) return;
                    if (isInitialized) {
                        await stopScanner();
                    }

                    isInitializing = true;
                    try {
                        const readerElement = document.getElementById('qr-reader');
                        if (!readerElement) throw new Error('Scanner element not found');

                        updateDebugInfo('Starting camera scanner...', false);
                        
                        // Clear previous content
                        readerElement.innerHTML = '';
                        
                        html5QrCode = new Html5Qrcode("qr-reader", { verbose: false });
                        
                        const config = { 
                            fps: 10, 
                            qrbox: { width: 250, height: 250 },
                            aspectRatio: 1.0
                        };

                        await html5QrCode.start(
                            { facingMode: "environment" }, 
                            config,
                            onScanSuccess
                        );

                        updateDebugInfo('✓ Camera active - Ready to scan', false);
                        isInitialized = true;
                    } catch (error) {
                        console.error('Camera initialization error:', error);
                        updateDebugInfo(`Error: ${error.message}`, true);
                        $wire.call('scannerError', 'Unable to access camera. Please check permissions.', 'Camera Error');
                    } finally {
                        isInitializing = false;
                    }
                }

                /**
                 * Success callback for camera scans
                 */
                function onScanSuccess(decodedText, decodedResult) {
                    console.log('QR Code detected!', decodedResult);
                    scanCount++;
                    
                    // Pause scanning immediately to prevent duplicate calls
                    if (html5QrCode) {
                        html5QrCode.pause();
                    }

                    updateDebugInfo(`✓ Scanned successfully (#${scanCount})`, false);
                    
                    // Stop scanner and send to backend
                    stopScanner().then(() => {
                        $wire.call('handleScan', decodedText);
                    });
                }

                /**
                 * Unified cleanup and stop function
                 */
                async function stopScanner() {
                    console.log('stopScanner called, isInitialized:', isInitialized);
                    
                    if (html5QrCode && html5QrCode.isScanning) {
                        try {
                            await html5QrCode.stop();
                            console.log('Scanner stopped');
                        } catch (err) {
                            console.warn('Error stopping scanner:', err);
                        }
                    }

                    const readerElement = document.getElementById('qr-reader');
                    if (readerElement) readerElement.innerHTML = '';
                    
                    const fileReaderElement = document.getElementById('file-qr-reader');
                    if (fileReaderElement) fileReaderElement.innerHTML = '';

                    html5QrCode = null;
                    isInitialized = false;
                    isInitializing = false;
                }

                // Global exposure for Alpine
                window.forceStopScanner = stopScanner;

                /**
                 * Initialize file upload scanner
                 */
                window.initFileUploadScanner = function() {
                    console.log('Initializing file upload handler');
                    const fileInput = document.getElementById('qr-input-file');
                    if (!fileInput) return;

                    // Clone to remove old listeners
                    const newFileInput = fileInput.cloneNode(true);
                    fileInput.parentNode.replaceChild(newFileInput, fileInput);

                    newFileInput.addEventListener('change', async function(e) {
                        if (!e.target.files || e.target.files.length === 0) return;
                        
                        const imageFile = e.target.files[0];
                        const fileReaderElement = document.getElementById('file-qr-reader');
                        if (!fileReaderElement) return;

                        fileReaderElement.innerHTML = 
                            '<div class="flex flex-col items-center justify-center h-full"><span class="loading loading-spinner loading-lg text-primary"></span><p class="text-sm mt-2">Scanning file...</p></div>';

                        try {
                            const scanner = new Html5Qrcode("file-qr-reader");
                            const decodedText = await scanner.scanFile(imageFile, true);
                            
                            console.log('✓ File scan succeeded');
                            fileReaderElement.innerHTML = 
                                '<div class="flex flex-col items-center justify-center h-full text-success"><x-mary-icon name="o-check-circle" class="w-16 h-16 mb-2" /><p class="text-sm font-semibold">QR code detected!</p></div>';
                            
                            await $wire.call('handleFileUploadScan', decodedText);
                        } catch (err) {
                            console.error('File scan failed, trying fallback...', err);
                            // Fallback to jsQR for robustness if available
                            if (window.jsQR) {
                                try {
                                    const result = await scanWithJsQR(imageFile);
                                    if (result) {
                                        await $wire.call('handleFileUploadScan', result);
                                        return;
                                    }
                                } catch (jsQrErr) {
                                    console.error('jsQR fallback also failed');
                                }
                            }
                            
                            fileReaderElement.innerHTML = 
                                '<div class="flex flex-col items-center justify-center h-full text-error"><x-mary-icon name="o-x-circle" class="w-16 h-16 mb-2" /><p class="text-sm font-semibold">Could not scan image</p></div>';
                            $wire.call('scannerError', 'Could not detect QR code in this image.', 'Scan Failed');
                        } finally {
                            newFileInput.value = '';
                        }
                    });
                }

                /**
                 * Fallback jsQR scanning logic for files
                 */
                async function scanWithJsQR(imageFile) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = new Image();
                            img.onload = () => {
                                const canvas = document.createElement('canvas');
                                const ctx = canvas.getContext('2d');
                                canvas.width = img.width;
                                canvas.height = img.height;
                                ctx.drawImage(img, 0, 0);
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = window.jsQR(imageData.data, imageData.width, imageData.height);
                                resolve(code ? code.data : null);
                            };
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(imageFile);
                    });
                }

                // Lifecycle hooks
                document.addEventListener('livewire:navigating', stopScanner);
                window.addEventListener('beforeunload', stopScanner);
                window.addEventListener('scanner-stopped', stopScanner);
            </script>
        @endscript
    @endif
</div>
