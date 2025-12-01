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
            <div class="bg-base-100 rounded-2xl shadow-2xl p-4 sm:p-6 w-full mx-4 max-h-[90vh] overflow-y-auto"
                :class="scanMode === null ? 'max-w-md' : 'max-w-full sm:max-w-4xl'">
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
                    <button @click="selectMode('camera')"
                        class="w-full btn btn-lg btn-primary justify-start gap-4 h-auto py-6">
                        <div class="bg-primary-content/20 p-3 rounded-lg">
                            <x-mary-icon name="o-camera" class="w-8 h-8" />
                        </div>
                        <div class="text-left">
                            <div class="font-bold text-lg">Scan with Camera</div>
                            <div class="text-sm opacity-80 font-normal">Use your device camera to scan QR code</div>
                        </div>
                    </button>

                    <button @click="selectMode('file')"
                        class="w-full btn btn-lg btn-secondary justify-start gap-4 h-auto py-6">
                        <div class="bg-secondary-content/20 p-3 rounded-lg">
                            <x-mary-icon name="o-photo" class="w-8 h-8" />
                        </div>
                        <div class="text-left">
                            <div class="font-bold text-lg">Upload QR Image</div>
                            <div class="text-sm opacity-80 font-normal">Select a QR code image from your device</div>
                        </div>
                    </button>
                </div>

                {{-- Camera Scanner Mode --}}
                <div x-show="scanMode === 'camera'" x-transition>
                    <button @click="reset()" class="btn btn-sm btn-ghost mb-4">
                        <x-mary-icon name="o-arrow-left" class="w-4 h-4" />
                        Back to selection
                    </button>

                    {{-- Camera Selector --}}
                    <div id="camera-selector" class="mb-4 hidden">
                        <label class="label">
                            <span class="label-text font-semibold flex items-center gap-2">
                                <x-mary-icon name="o-camera" class="w-4 h-4" />
                                Select Camera:
                            </span>
                        </label>
                        <select id="camera-select" class="select select-bordered w-full">
                            <option value="">Loading cameras...</option>
                        </select>
                    </div>

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
                    <div class="relative mb-4">
                        {{-- Decorative scanning frame --}}
                        <div class="absolute inset-0 pointer-events-none z-10">
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

                        <div id="qr-reader"
                            class="w-full rounded-xl overflow-hidden bg-gray-900 shadow-inner aspect-video sm:aspect-auto"
                            style="min-height: 300px;"></div>
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
                            class="w-full rounded-xl overflow-hidden bg-gray-900 aspect-video sm:aspect-auto"
                            style="min-height: 300px;"></div>
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
                let html5QrCode = null;
                let fileQrCode = null;
                let isInitialized = false;
                let isInitializing = false;
                let availableCameras = [];
                let currentCameraId = null;
                let scannerConfig = null;
                let successCallback = null;
                let errorCallback = null;
                let scanCount = 0;
                let hasLoggedInitialMessage = false;


                console.log('QR Scanner script loaded');

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

                // Initialize CAMERA scanner (only when camera mode is selected)
                window.initCameraScanner = async function() {
                    console.log('initCameraScanner called');

                    if (isInitializing) {
                        console.log('Already initializing, skipping...');
                        return;
                    }

                    if (isInitialized || html5QrCode) {
                        console.log('Forcing cleanup before reinitializing');
                        isInitializing = true;
                        try {
                            await stopScanner();
                            await new Promise(resolve => setTimeout(resolve, 200));
                            await initCameraScannerImpl();
                        } catch (error) {
                            console.error('Error during reinitialization:', error);
                            isInitializing = false;
                        }
                        return;
                    }

                    isInitializing = true;
                    try {
                        await initCameraScannerImpl();
                    } catch (error) {
                        console.error('Error during initialization:', error);
                        isInitializing = false;
                    }
                }

                function initCameraScannerImpl() {
                    return new Promise((resolve, reject) => {
                        setTimeout(() => {
                            const readerElement = document.getElementById('qr-reader');
                            console.log('QR reader element:', readerElement);

                            if (!readerElement) {
                                console.error('QR reader element not found');
                                isInitializing = false;
                                reject(new Error('QR reader element not found'));
                                return;
                            }

                            try {
                                console.log('Creating Html5Qrcode instance for camera');
                                html5QrCode = new Html5Qrcode("qr-reader");

                                // Responsive QR box sizing
                                const isMobile = window.innerWidth < 640;
                                const qrBoxSize = isMobile ?
                                    Math.min(250, window.innerWidth - 80) :
                                    Math.min(350, window.innerWidth - 100);

                                scannerConfig = {
                                    fps: 10,
                                    qrbox: {
                                        width: qrBoxSize,
                                        height: qrBoxSize
                                    },
                                    aspectRatio: isMobile ? 1.0 : 1.777778 // 1:1 for mobile, 16:9 for desktop
                                };

                                successCallback = (decodedText) => {
                                    scanCount++;
                                    updateDebugInfo(`✓ QR Code scanned successfully! (Scan #${scanCount})`,
                                        false);
                                    updateDebugInfo(`Data length: ${decodedText.length} characters`, false);
                                    console.log('QR Code scanned:', decodedText);

                                    // Validation result logging
                                    console.group('📋 QR Code Validation');
                                    console.log('Source: Camera Scan');
                                    console.log('Timestamp:', new Date().toISOString());
                                    console.log('Data Length:', decodedText.length);
                                    console.log('Scan Count:', scanCount);
                                    console.groupEnd();

                                    // Stop scanning immediately
                                    stopScanner().then(() => {
                                        updateDebugInfo('Sending data to server...', false);
                                        $wire.call('handleScan', decodedText);
                                    });
                                };

                                errorCallback = (errorMessage) => {
                                    // Silently handle scanning errors (too verbose otherwise)
                                    // Only log periodically to avoid spam
                                    if (!hasLoggedInitialMessage && scanCount === 0) {
                                        updateDebugInfo('Scanning... waiting for QR code', false);
                                        hasLoggedInitialMessage = true;
                                    }
                                };

                                console.log('Requesting camera access...');

                                // Try to get camera devices first
                                Html5Qrcode.getCameras().then(devices => {
                                    console.log('Available cameras:', devices);
                                    updateDebugInfo(`Found ${devices.length} camera(s)`, false);
                                    availableCameras = devices;

                                    if (devices && devices.length) {
                                        // Populate camera selector
                                        const cameraSelect = document.getElementById('camera-select');
                                        const cameraSelector = document.getElementById('camera-selector');

                                        if (devices.length > 1 && cameraSelect) {
                                            cameraSelector.classList.remove('hidden');
                                            cameraSelect.innerHTML = '';
                                            devices.forEach((device, index) => {
                                                const option = document.createElement('option');
                                                option.value = device.id;
                                                option.text = device.label || `Camera ${index + 1}`;
                                                cameraSelect.appendChild(option);
                                            });

                                            // Store and cleanup event listener
                                            if (window._cameraChangeListenerRef) {
                                                cameraSelect.removeEventListener('change', window
                                                    ._cameraChangeListenerRef);
                                            }
                                            window._cameraChangeListenerRef = function() {
                                                switchCamera(this.value, scannerConfig, successCallback,
                                                    errorCallback);
                                            };
                                            cameraSelect.addEventListener('change', window
                                                ._cameraChangeListenerRef);
                                        }

                                        // Filter out virtual cameras and prefer real cameras
                                        const realCameras = devices.filter(device => {
                                            const label = device.label.toLowerCase();
                                            // Filter out OBS, virtual cameras, etc.
                                            return !label.includes('obs') &&
                                                !label.includes('virtual') &&
                                                !label.includes('snap');
                                        });

                                        console.log('Real cameras found:', realCameras);

                                        // Prefer back camera, then any real camera, then fallback to any camera
                                        let selectedCamera;
                                        if (realCameras.length > 0) {
                                            // Try to find back/rear camera first
                                            const backCamera = realCameras.find(c =>
                                                c.label.toLowerCase().includes('back') ||
                                                c.label.toLowerCase().includes('rear')
                                            );
                                            selectedCamera = backCamera || realCameras[0];
                                        } else {
                                            selectedCamera = devices[0]; // Fallback to first available
                                        }

                                        console.log('Selected camera:', selectedCamera);
                                        updateDebugInfo(
                                            `Using camera: ${selectedCamera.label || selectedCamera.id}`,
                                            false);
                                        currentCameraId = selectedCamera.id;

                                        const finalSelect = document.getElementById('camera-select');
                                        if (finalSelect && devices.length > 1) {
                                            finalSelect.value = currentCameraId;
                                        }

                                        updateDebugInfo('Starting camera...', false);
                                        startCamera(currentCameraId, scannerConfig, successCallback,
                                            errorCallback);
                                        isInitialized = true;
                                        isInitializing = false;
                                        resolve();
                                    } else {
                                        $wire.call('scannerError',
                                            'No camera found on this device. Please connect a camera or use the file upload option.',
                                            'No Camera Detected');
                                        $wire.call('stopScanning');
                                        isInitialized = false;
                                        isInitializing = false;
                                        reject(new Error('No camera found'));
                                    }
                                }).catch(err => {
                                    console.error('Error getting cameras:', err);
                                    updateDebugInfo(
                                        'Camera access failed. Please check permissions or use "Upload QR Image" instead.',
                                        true
                                    );

                                    // Surface a clear error back to Livewire
                                    $wire.call(
                                        'scannerError',
                                        'Unable to access any camera on this device. Please ensure camera permissions are granted, then try again or use the "Upload QR Image" option instead.',
                                        'Camera Access Failed'
                                    );

                                    // Reset state and clean up instance
                                    isInitializing = false;
                                    isInitialized = false;
                                    try {
                                        if (html5QrCode) {
                                            html5QrCode.clear();
                                        }
                                    } catch (clearErr) {
                                        console.debug(
                                            'Error clearing scanner after getCameras failure:',
                                            clearErr
                                        );
                                    }
                                    html5QrCode = null;

                                    reject(err);
                                });

                            } catch (error) {
                                console.error('Scanner initialization error:', error);
                                $wire.call('scannerError', 'Failed to initialize scanner: ' + error.message,
                                    'Initialization Failed');
                                $wire.call('stopScanning');
                                isInitialized = false;
                                isInitializing = false;
                                html5QrCode = null;
                                reject(error);
                            }
                        }, 100);
                    });
                }

                // Start camera with specific ID
                function startCamera(cameraId, config, successCb, errorCb) {
                    if (!html5QrCode) {
                        console.error('html5QrCode not initialized');
                        return;
                    }

                    html5QrCode.start(
                        cameraId,
                        config,
                        successCb,
                        errorCb
                    ).then(() => {
                        console.log('Camera started successfully');
                        updateDebugInfo('✓ Camera started - Ready to scan!', false);
                    }).catch(err => {
                        console.error('Error starting camera:', err);

                        // Try with facingMode as fallback
                        console.log('Trying facingMode fallback...');
                        html5QrCode.start({
                                facingMode: "user"
                            }, // Try front camera
                            config,
                            successCb,
                            errorCb
                        ).catch(fallbackErr => {
                            console.error('Fallback also failed:', fallbackErr);
                            $wire.call('scannerError',
                                'Unable to access camera. Please ensure camera permissions are granted and no other app is using the camera.',
                                'Camera Access Failed');
                            $wire.call('stopScanning');
                            isInitialized = false;
                            html5QrCode = null;
                        });
                    });
                }

                // Switch to different camera
                function switchCamera(cameraId, config, successCb, errorCb) {
                    if (html5QrCode && isInitialized) {
                        html5QrCode.stop().then(() => {
                            currentCameraId = cameraId;
                            startCamera(cameraId, config, successCb, errorCb);
                        }).catch(err => {
                            console.error('Error switching camera:', err);
                        });
                    }
                }

                // Cleanup function
                function stopScanner() {
                    console.log('stopScanner called, isInitialized:', isInitialized);
                    return new Promise((resolve) => {
                        if (html5QrCode) {
                            try {
                                const state = html5QrCode.getState();
                                console.log('Scanner state:', state);

                                // Only stop if scanner is running (state 2 = SCANNING)
                                if (state === 2) {
                                    html5QrCode.stop()
                                        .then(() => {
                                            console.log('Scanner stopped successfully');
                                            cleanupScanner();
                                            resolve();
                                        })
                                        .catch(err => {
                                            console.error('Error stopping scanner:', err);
                                            cleanupScanner();
                                            resolve();
                                        });
                                } else {
                                    console.log('Scanner not running, just clearing');
                                    cleanupScanner();
                                    resolve();
                                }
                            } catch (error) {
                                console.error('Error in stopScanner:', error);
                                cleanupScanner();
                                resolve();
                            }
                        } else {
                            console.log('No scanner instance to stop');
                            isInitialized = false;
                            resolve();
                        }
                    });
                }

                // Helper function to cleanup scanner resources
                function cleanupScanner() {
                    try {
                        if (html5QrCode) {
                            html5QrCode.clear();
                        }
                    } catch (e) {
                        console.debug('Clear error:', e);
                    }
                    html5QrCode = null;
                    isInitialized = false;
                    isInitializing = false;
                    scanCount = 0;
                    hasLoggedInitialMessage = false;

                    // Also cleanup file scanner if needed
                    try {
                        if (fileQrCode) {
                            fileQrCode.clear();
                        }
                    } catch (e) {
                        console.debug('File scanner clear error:', e);
                    }
                    fileQrCode = null;
                    // Reset camera selector
                    const cameraSelector = document.getElementById('camera-selector');
                    if (cameraSelector) {
                        cameraSelector.classList.add('hidden');
                    }

                    // Clear qr-reader content
                    const qrReader = document.getElementById('qr-reader');
                    if (qrReader) {
                        qrReader.innerHTML = '';
                    }

                    console.log('Scanner cleanup complete');
                }

                // Make stopScanner available globally for button onclick
                window.forceStopScanner = function() {
                    console.log('Force stop scanner triggered');
                    stopScanner();
                };

                // Initialize file upload scanner (called when file mode is selected)
                window.initFileUploadScanner = function() {
                    console.log('Initializing file upload scanner');

                    const fileInput = document.getElementById('qr-input-file');
                    const fileReaderElement = document.getElementById('file-qr-reader');

                    if (!fileInput) {
                        console.error('qr-input-file element not found');
                        return;
                    }

                    if (!fileReaderElement) {
                        console.error('file-qr-reader element not found');
                        return;
                    }

                    console.log('File upload scanner elements found, setting up handler');

                    // Remove any existing listeners by cloning
                    const newFileInput = fileInput.cloneNode(true);
                    fileInput.parentNode.replaceChild(newFileInput, fileInput);

                    // Add click event to ensure file dialog opens properly
                    newFileInput.addEventListener('click', function(e) {
                        console.log('File input clicked');
                        // Ensure the input is ready
                        this.value = '';
                    });

                    newFileInput.addEventListener('change', function(e) {
                        console.log('File input change event triggered');
                        console.log('Event target:', e.target);
                        console.log('Files:', e.target.files);

                        // Defensive checks
                        if (!e || !e.target) {
                            console.error('Invalid event object');
                            return;
                        }

                        if (!e.target.files || e.target.files.length === 0) {
                            console.log('No files selected');
                            return;
                        }

                        const imageFile = e.target.files[0];
                        console.log('File upload selected:', imageFile.name);
                        console.log('File size:', imageFile.size, 'bytes');
                        console.log('File type:', imageFile.type);

                        // Check if file-qr-reader element exists
                        const fileReaderElement = document.getElementById('file-qr-reader');
                        if (!fileReaderElement) {
                            console.error('file-qr-reader element not found. File upload mode may not be active.');
                            $wire.call('scannerError', 'Scanner not ready. Please select "Upload QR Image" mode first.',
                                'Scanner Error');
                            e.target.value = '';
                            return;
                        }

                        // Show loading feedback
                        fileReaderElement.innerHTML =
                            '<div class="flex items-center justify-center h-full"><span class="loading loading-spinner loading-lg text-primary"></span></div>';

                        // Always create a fresh Html5Qrcode instance for file scanning to avoid state issues
                        try {
                            // Clean up existing instance if any
                            if (fileQrCode) {
                                try {
                                    fileQrCode.clear().catch((cleanupErr) => {
                                        console.warn('File QR scanner cleanup failed:', cleanupErr);
                                    });
                                } catch (err) {
                                    console.warn('File QR scanner cleanup threw:', err);
                                }
                            }

                            // Create new instance
                            fileQrCode = new Html5Qrcode("file-qr-reader");
                            console.log('Created fresh file scanner instance');
                        } catch (error) {
                            console.error('Error creating file scanner:', error);
                            fileReaderElement.innerHTML =
                                '<div class="flex flex-col items-center justify-center h-full text-error"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><p class="text-sm">Scanner initialization failed</p></div>';
                            $wire.call('scannerError', 'Could not initialize QR scanner.', 'Scanner Error');
                            e.target.value = '';
                            return;
                        }

                        console.log('Scanning file...');
                        fileQrCode.scanFile(imageFile, true)
                            .then(decodedText => {
                                console.log('✓ QR Code decoded from file');
                                console.log('Decoded text length:', decodedText.length);
                                // Unicode-safe truncate for logging
                                let preview = decodedText;
                                if ([...decodedText].length > 100) {
                                    preview = [...decodedText].slice(0, 100).join('') + '...';
                                }
                                console.log('Decoded text preview:', preview);

                                // Show processing feedback
                                fileReaderElement.innerHTML =
                                    '<div class="flex flex-col items-center justify-center h-full text-info"><span class="loading loading-spinner loading-lg mb-2"></span><p class="text-sm">Processing QR code...</p></div>';

                                // Validation result logging
                                console.group('📋 QR Code Validation');
                                console.log('Source: File Upload');
                                console.log('Timestamp:', new Date().toISOString());
                                console.log('Data Length:', decodedText.length);
                                console.groupEnd();

                                // Send to backend for processing and wait for response
                                $wire.call('handleFileUploadScan', decodedText)
                                    .then(() => {
                                        console.log('✓ Backend processing completed successfully');
                                        // Show success feedback
                                        fileReaderElement.innerHTML =
                                            '<div class="flex flex-col items-center justify-center h-full text-success"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><p class="text-sm">QR code processed!</p></div>';
                                        
                                        // Reset file input after successful processing
                                        setTimeout(() => {
                                            e.target.value = '';
                                        }, 500);
                                    })
                                    .catch(error => {
                                        console.error('❌ Backend processing failed:', error);
                                        // Show error feedback
                                        fileReaderElement.innerHTML =
                                            '<div class="flex flex-col items-center justify-center h-full text-error"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><p class="text-sm">Processing failed</p></div>';
                                        
                                        // Reset file input to allow retry
                                        e.target.value = '';
                                    });
                            })
                            .catch(err => {
                                console.error('❌ Error scanning file:', err);
                                console.log('File name:', imageFile.name);
                                console.log('Error details:', err.message || err);

                                // Show error feedback
                                fileReaderElement.innerHTML =
                                    '<div class="flex flex-col items-center justify-center h-full text-error"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><p class="text-sm">Could not scan QR code</p></div>';

                                $wire.call('scannerError',
                                    'Could not scan QR code from image. Please ensure the image contains a valid, clear QR code.',
                                    'Scan Failed');

                                // Reset file input
                                e.target.value = '';
                            });
                    });

                    console.log('File upload scanner initialized successfully');
                };

                // Listen for when isScanning becomes false (modal closes)
                Livewire.hook('morph.updated', ({
                    el,
                    component
                }) => {
                    // Check if modal is being closed
                    if (!document.getElementById('qr-reader')) {
                        console.log('Scanner modal closed, stopping camera');
                        stopScanner();
                    }
                });

                // Cleanup on component destruction
                document.addEventListener('livewire:navigating', () => {
                    console.log('Livewire navigating, cleanup');
                    stopScanner();
                });

                window.addEventListener('beforeunload', () => {
                    console.log('Page unload, cleanup');
                    stopScanner();
                });
            </script>
        @endscript
    @endif
</div>
