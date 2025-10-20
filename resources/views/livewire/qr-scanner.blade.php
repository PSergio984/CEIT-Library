<div x-data="{ scanning: @entangle('isScanning') }" x-init="
    $watch('scanning', value => {
        console.log('Scanning state changed to:', value);
        if (!value) {
            console.log('Modal closing, stopping camera');
            if (window.forceStopScanner) {
                window.forceStopScanner();
            }
        } else {
            console.log('Modal opening, should initialize camera');
            // Give time for DOM to render
            setTimeout(() => {
                if (window.reinitScanner) {
                    window.reinitScanner();
                }
            }, 150);
        }
    })
">
    @if($isScanning)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-data>
            <div class="bg-base-100 rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Scan QR Code</h3>
                    <button wire:click="stopScanning" class="btn btn-sm btn-circle btn-ghost">
                        <x-mary-icon name="o-x-mark" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Camera Selector --}}
                <div id="camera-selector" class="mb-4 hidden">
                    <label class="label">
                        <span class="label-text font-semibold">Select Camera:</span>
                    </label>
                    <select id="camera-select" class="select select-bordered w-full select-sm">
                        <option value="">Loading cameras...</option>
                    </select>
                </div>

                {{-- QR Scanner Container --}}
                <div id="qr-reader" class="w-full rounded-lg overflow-hidden bg-gray-800" style="min-height: 300px;"></div>

                {{-- File Upload Fallback --}}
                <div id="qr-file-upload" class="hidden mt-4">
                    <div class="text-center mb-4 text-warning">
                        <p class="font-semibold">Camera not available</p>
                        <p class="text-sm">Upload QR code image instead</p>
                    </div>
                    <input type="file" id="qr-input-file" accept="image/*" 
                           class="file-input file-input-bordered w-full" />
                </div>

                <div class="mt-4 text-center text-sm text-muted-foreground">
                    Position the QR code within the camera frame
                </div>
            </div>
        </div>

        @script
        <script>
            let html5QrCode = null;
            let isInitialized = false;
            let isInitializing = false;
            let availableCameras = [];
            let currentCameraId = null;
            let scannerConfig = null;
            let successCallback = null;
            let errorCallback = null;

            console.log('QR Scanner script loaded');

            // Initialize scanner when modal opens
            async function initScanner() {
                console.log('initScanner called, isInitialized:', isInitialized, 'isInitializing:', isInitializing);
                
                // Prevent concurrent initialization
                if (isInitializing) {
                    console.log('Already initializing, skipping...');
                    return;
                }
                
                // Force cleanup if already initialized
                if (isInitialized || html5QrCode) {
                    console.log('Forcing cleanup before reinitializing');
                    isInitializing = true;
                    try {
                        await stopScanner();
                        // Check if modal is still open
                        if (!document.getElementById('qr-reader')) {
                            console.log('Modal closed during cleanup, aborting reinit');
                            isInitializing = false;
                            return;
                        }
                        await new Promise(resolve => setTimeout(resolve, 200));
                        // Check again after delay
                        if (!document.getElementById('qr-reader')) {
                            console.log('Modal closed during delay, aborting reinit');
                            isInitializing = false;
                            return;
                        }
                        await initScannerImpl();
                    } catch (error) {
                        console.error('Error during reinitialization:', error);
                        // Handle fallback mode gracefully - don't show error to user
                        if (!error.isFallback) {
                            console.error('Non-fallback error during reinitialization');
                        }
                        isInitializing = false;
                    }
                    return;
                }
                
                isInitializing = true;
                try {
                    await initScannerImpl();
                } catch (error) {
                    // Handle fallback mode gracefully - don't show error to user
                    if (error.isFallback) {
                        console.log('Scanner in fallback mode (file upload)');
                    } else {
                        console.error('Error during initialization:', error);
                    }
                    isInitializing = false;
                }
            }

            function initScannerImpl() {
                return new Promise((resolve, reject) => {
                    // Wait for DOM to be ready
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
                            console.log('Creating Html5Qrcode instance');
                            html5QrCode = new Html5Qrcode("qr-reader");
                        
                        scannerConfig = {
                            fps: 10,
                            qrbox: { width: 250, height: 250 }
                        };

                        successCallback = (decodedText) => {
                            console.log('QR Code scanned:', decodedText);
                            // Stop scanning immediately
                            stopScanner().then(() => {
                                $wire.call('handleScan', decodedText);
                            });
                        };

                        errorCallback = (errorMessage) => {
                            // Silently handle scanning errors (too verbose otherwise)
                        };

                        console.log('Requesting camera access...');
                        
                        // Try to get camera devices first
                        Html5Qrcode.getCameras().then(devices => {
                            console.log('Available cameras:', devices);
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
                                    
                                    // Remove old listener if exists
                                    const newSelect = cameraSelect.cloneNode(true);
                                    cameraSelect.parentNode.replaceChild(newSelect, cameraSelect);
                                    
                                    // Handle camera change
                                    newSelect.addEventListener('change', function() {
                                        switchCamera(this.value, scannerConfig, successCallback, errorCallback);
                                    });
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
                                currentCameraId = selectedCamera.id;
                                
                                const finalSelect = document.getElementById('camera-select');
                                if (finalSelect && devices.length > 1) {
                                    finalSelect.value = currentCameraId;
                                }
                                
                                startCamera(currentCameraId, scannerConfig, successCallback, errorCallback);
                                isInitialized = true;
                                isInitializing = false;
                                resolve();
                            } else {
                                alert('No camera found on this device.');
                                $wire.call('stopScanning');
                                isInitialized = false;
                                isInitializing = false;
                                reject(new Error('No camera found'));
                            }
                        }).catch(err => {
                            console.error('Error getting cameras:', err);
                            isInitializing = false;
                            
                            // Show file upload fallback
                            const qrReader = document.getElementById('qr-reader');
                            const fileUpload = document.getElementById('qr-file-upload');
                            
                            if (qrReader) qrReader.classList.add('hidden');
                            if (fileUpload) fileUpload.classList.remove('hidden');
                            
                            // Setup file upload handler
                            const fileInput = document.getElementById('qr-input-file');
                            if (fileInput) {
                                fileInput.addEventListener('change', function(e) {
                                    if (e.target.files.length === 0) return;
                                    
                                    const imageFile = e.target.files[0];
                                    html5QrCode.scanFile(imageFile, true)
                                        .then(decodedText => {
                                            console.log('QR Code from file:', decodedText);
                                            $wire.call('handleScan', decodedText);
                                            stopScanner();
                                        })
                                        .catch(err => {
                                            console.error('Error scanning file:', err);
                                            alert('Could not scan QR code from image. Please try another image.');
                                        });
                                });
                            }
                            
                            console.log('Fallback to file upload enabled');
                            // Reject with specific error to indicate fallback mode
                            const fallbackError = new Error('Camera initialization failed, using file upload fallback');
                            fallbackError.isFallback = true;
                            fallbackError.originalError = err;
                            reject(fallbackError);
                        });
                        
                    } catch (error) {
                        console.error('Scanner initialization error:', error);
                        alert('Failed to initialize scanner: ' + error.message);
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
                }).catch(err => {
                    console.error('Error starting camera:', err);
                    
                    // Try with facingMode as fallback
                    console.log('Trying facingMode fallback...');
                    html5QrCode.start(
                        { facingMode: "user" }, // Try front camera
                        config,
                        successCb,
                        errorCb
                    ).catch(fallbackErr => {
                        console.error('Fallback also failed:', fallbackErr);
                        alert('Unable to access camera. Please ensure:\n1. Camera permissions are granted\n2. No other app is using the camera');
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
                } catch(e) {
                    console.debug('Clear error:', e);
                }
                html5QrCode = null;
                isInitialized = false;
                isInitializing = false;
                
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

            // Make reinitScanner available globally
            window.reinitScanner = function() {
                console.log('Reinit scanner triggered');
                // Only reinit if not already initialized and element exists
                const elem = document.getElementById('qr-reader');
                if (elem && !isInitialized) {
                    initScanner();
                } else if (elem && isInitialized) {
                    console.log('Already initialized, skipping reinit');
                } else {
                    console.log('Element not found, cannot reinit');
                }
            };

            // Initialize on mount
            console.log('Calling initScanner');
            initScanner();

            // Listen for when isScanning becomes false (modal closes)
            Livewire.hook('morph.updated', ({ el, component }) => {
                // Check if modal is being closed
                if (!document.getElementById('qr-reader')) {
                    console.log('Scanner modal closed, stopping camera');
                    stopScanner();
                }
                
                const scannerElement = document.getElementById('qr-reader');
                if (scannerElement && !isInitialized) {
                    console.log('Scanner element detected after morph, reinitializing');
                    setTimeout(() => initScanner(), 100);
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
