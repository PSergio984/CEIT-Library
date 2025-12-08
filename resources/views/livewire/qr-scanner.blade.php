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
                // jsQR is available globally via window.jsQR (imported in app.js)
                
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
                
                // jsQR camera scanning variables
                let videoElement = null;
                let canvasElement = null;
                let canvasContext = null;
                let animationFrameId = null;
                let jsQrScanInterval = null;
                let isJsQrScanning = false;


                console.log('QR Scanner script loaded (jsQR primary for both camera and file upload)');

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
                 * Initialize camera scanner using jsQR (primary method)
                 * Falls back to html5-qrcode only if jsQR camera fails
                 */
                window.initCameraScanner = async function() {
                    console.log('initCameraScanner called (jsQR primary)');

                    if (isInitializing) {
                        console.log('Already initializing, skipping...');
                        return;
                    }

                    if (isInitialized) {
                        console.log('Forcing cleanup before reinitializing');
                        isInitializing = true;
                        try {
                            await stopScanner();
                            await new Promise(resolve => setTimeout(resolve, 200));
                            await initJsQrCameraScanner();
                        } catch (error) {
                            console.error('Error during reinitialization:', error);
                            isInitializing = false;
                        }
                        return;
                    }

                    isInitializing = true;
                    try {
                        await initJsQrCameraScanner();
                    } catch (error) {
                        console.error('Error during initialization:', error);
                        isInitializing = false;
                    }
                }

                /**
                 * Initialize jsQR-based camera scanner
                 */
                async function initJsQrCameraScanner() {
                    return new Promise(async (resolve, reject) => {
                        const readerElement = document.getElementById('qr-reader');
                        console.log('QR reader element:', readerElement);

                        if (!readerElement) {
                            console.error('QR reader element not found');
                            isInitializing = false;
                            reject(new Error('QR reader element not found'));
                            return;
                        }

                        try {
                            updateDebugInfo('Initializing jsQR camera scanner...', false);
                            
                            // Create video element for camera feed
                            videoElement = document.createElement('video');
                            videoElement.setAttribute('playsinline', 'true');
                            videoElement.setAttribute('autoplay', 'true');
                            videoElement.style.width = '100%';
                            videoElement.style.height = '100%';
                            videoElement.style.objectFit = 'cover';
                            videoElement.style.borderRadius = '0.75rem';
                            
                            // Create hidden canvas for frame capture
                            canvasElement = document.createElement('canvas');
                            canvasContext = canvasElement.getContext('2d', { willReadFrequently: true });
                            
                            // Clear the reader element and add video
                            readerElement.innerHTML = '';
                            readerElement.appendChild(videoElement);
                            
                            // Get available cameras
                            const devices = await navigator.mediaDevices.enumerateDevices();
                            const cameras = devices.filter(device => device.kind === 'videoinput');
                            
                            console.log('Available cameras:', cameras);
                            updateDebugInfo(`Found ${cameras.length} camera(s)`, false);
                            availableCameras = cameras;
                            
                            if (cameras.length === 0) {
                                throw new Error('No camera found');
                            }
                            
                            // Populate camera selector if multiple cameras
                            const cameraSelect = document.getElementById('camera-select');
                            const cameraSelector = document.getElementById('camera-selector');
                            
                            if (cameras.length > 1 && cameraSelect && cameraSelector) {
                                cameraSelector.classList.remove('hidden');
                                cameraSelect.innerHTML = '';
                                cameras.forEach((camera, index) => {
                                    const option = document.createElement('option');
                                    option.value = camera.deviceId;
                                    option.text = camera.label || `Camera ${index + 1}`;
                                    cameraSelect.appendChild(option);
                                });
                                
                                // Setup camera switch listener
                                if (window._cameraChangeListenerRef) {
                                    cameraSelect.removeEventListener('change', window._cameraChangeListenerRef);
                                }
                                window._cameraChangeListenerRef = function() {
                                    switchToCamera(this.value);
                                };
                                cameraSelect.addEventListener('change', window._cameraChangeListenerRef);
                            }
                            
                            // Filter out virtual cameras and prefer back camera
                            const realCameras = cameras.filter(device => {
                                const label = (device.label || '').toLowerCase();
                                return !label.includes('obs') && 
                                       !label.includes('virtual') && 
                                       !label.includes('snap');
                            });
                            
                            let selectedCamera;
                            if (realCameras.length > 0) {
                                const backCamera = realCameras.find(c => {
                                    const label = (c.label || '').toLowerCase();
                                    return label.includes('back') || label.includes('rear');
                                });
                                selectedCamera = backCamera || realCameras[0];
                            } else {
                                selectedCamera = cameras[0];
                            }
                            
                            currentCameraId = selectedCamera.deviceId;
                            updateDebugInfo(`Using camera: ${selectedCamera.label || 'Default'}`, false);
                            
                            if (cameraSelect && cameras.length > 1) {
                                cameraSelect.value = currentCameraId;
                            }
                            
                            // Start the camera
                            await startJsQrCamera(currentCameraId);
                            
                            isInitialized = true;
                            isInitializing = false;
                            resolve();
                            
                        } catch (error) {
                            console.error('jsQR camera initialization error:', error);
                            updateDebugInfo(`Camera error: ${error.message}`, true);
                            
                            $wire.call('scannerError', 
                                'Unable to access camera. Please ensure camera permissions are granted and try again.',
                                'Camera Access Failed');
                            
                            isInitialized = false;
                            isInitializing = false;
                            cleanupJsQrCamera();
                            reject(error);
                        }
                    });
                }
                
                /**
                 * Start jsQR camera with specific device ID
                 */
                async function startJsQrCamera(deviceId) {
                    try {
                        // Stop any existing stream
                        if (videoElement && videoElement.srcObject) {
                            const tracks = videoElement.srcObject.getTracks();
                            tracks.forEach(track => track.stop());
                        }
                        
                        // Camera constraints
                        const constraints = {
                            video: {
                                deviceId: deviceId ? { exact: deviceId } : undefined,
                                facingMode: deviceId ? undefined : 'environment',
                                width: { ideal: 1280 },
                                height: { ideal: 720 }
                            }
                        };
                        
                        updateDebugInfo('Requesting camera access...', false);
                        const stream = await navigator.mediaDevices.getUserMedia(constraints);
                        
                        videoElement.srcObject = stream;
                        
                        // Wait for video to be ready
                        await new Promise((resolve, reject) => {
                            videoElement.onloadedmetadata = () => {
                                videoElement.play()
                                    .then(() => resolve())
                                    .catch(reject);
                            };
                            videoElement.onerror = reject;
                        });
                        
                        updateDebugInfo('✓ Camera started - Ready to scan with jsQR!', false);
                        
                        // Set canvas size to match video
                        canvasElement.width = videoElement.videoWidth;
                        canvasElement.height = videoElement.videoHeight;
                        
                        // Start scanning loop
                        startJsQrScanLoop();
                        
                    } catch (error) {
                        console.error('Error starting camera:', error);
                        throw error;
                    }
                }
                
                /**
                 * Switch to different camera
                 */
                async function switchToCamera(deviceId) {
                    if (!isInitialized) return;
                    
                    try {
                        stopJsQrScanLoop();
                        currentCameraId = deviceId;
                        await startJsQrCamera(deviceId);
                        updateDebugInfo(`Switched to camera: ${deviceId}`, false);
                    } catch (error) {
                        console.error('Error switching camera:', error);
                        updateDebugInfo(`Failed to switch camera: ${error.message}`, true);
                    }
                }
                
                /**
                 * Start jsQR scanning loop - captures frames and scans for QR codes
                 */
                function startJsQrScanLoop() {
                    if (isJsQrScanning) return;
                    isJsQrScanning = true;
                    
                    let frameCount = 0;
                    let lastScanTime = 0;
                    const scanInterval = 100; // Scan every 100ms (10 fps for scanning)
                    
                    function scanFrame() {
                        if (!isJsQrScanning || !videoElement || !canvasContext) {
                            return;
                        }
                        
                        const now = Date.now();
                        
                        // Only scan at specified interval
                        if (now - lastScanTime >= scanInterval) {
                            lastScanTime = now;
                            frameCount++;
                            
                            try {
                                // Draw current video frame to canvas
                                canvasContext.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                                
                                // Get image data for jsQR
                                const imageData = canvasContext.getImageData(0, 0, canvasElement.width, canvasElement.height);
                                
                                // Scan with jsQR
                                const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                                    inversionAttempts: 'dontInvert' // Faster, most QR codes are dark on light
                                });
                                
                                if (code && code.data) {
                                    console.log('[jsQR Camera] ✓ QR code detected!');
                                    scanCount++;
                                    updateDebugInfo(`✓ QR Code scanned! (Scan #${scanCount})`, false);
                                    
                                    // Stop scanning and process
                                    stopJsQrScanLoop();
                                    
                                    // Validation logging
                                    console.group('📋 QR Code Validation');
                                    console.log('Source: jsQR Camera Scan');
                                    console.log('Timestamp:', new Date().toISOString());
                                    console.log('Data Length:', code.data.length);
                                    console.log('Scan Count:', scanCount);
                                    console.log('Frame:', frameCount);
                                    console.groupEnd();
                                    
                                    // Send to backend
                                    updateDebugInfo('Sending data to server...', false);
                                    stopScanner().then(() => {
                                        $wire.call('handleScan', code.data);
                                    });
                                    return;
                                }
                                
                                // Log periodic status
                                if (frameCount === 1) {
                                    updateDebugInfo('Scanning... waiting for QR code', false);
                                }
                                
                            } catch (error) {
                                console.error('Frame scan error:', error);
                            }
                        }
                        
                        // Continue scanning
                        animationFrameId = requestAnimationFrame(scanFrame);
                    }
                    
                    // Start the scanning loop
                    animationFrameId = requestAnimationFrame(scanFrame);
                    console.log('[jsQR] Camera scanning loop started');
                }
                
                /**
                 * Stop jsQR scanning loop
                 */
                function stopJsQrScanLoop() {
                    isJsQrScanning = false;
                    
                    if (animationFrameId) {
                        cancelAnimationFrame(animationFrameId);
                        animationFrameId = null;
                    }
                    
                    if (jsQrScanInterval) {
                        clearInterval(jsQrScanInterval);
                        jsQrScanInterval = null;
                    }
                    
                    console.log('[jsQR] Camera scanning loop stopped');
                }
                
                /**
                 * Cleanup jsQR camera resources
                 */
                function cleanupJsQrCamera() {
                    stopJsQrScanLoop();
                    
                    if (videoElement && videoElement.srcObject) {
                        const tracks = videoElement.srcObject.getTracks();
                        tracks.forEach(track => {
                            track.stop();
                            console.log('Camera track stopped:', track.label);
                        });
                        videoElement.srcObject = null;
                    }
                    
                    videoElement = null;
                    canvasElement = null;
                    canvasContext = null;
                }

                // Cleanup function
                function stopScanner() {
                    console.log('stopScanner called, isInitialized:', isInitialized);
                    return new Promise((resolve) => {
                        // Stop jsQR camera first
                        cleanupJsQrCamera();
                        
                        // Also cleanup html5QrCode if it was used
                        if (html5QrCode) {
                            try {
                                const state = html5QrCode.getState();
                                console.log('Scanner state:', state);

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
                            console.log('No html5QrCode instance to stop');
                            cleanupScanner();
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

                /**
                 * Scan QR code from image using jsQR (primary, more reliable)
                 * Returns decoded text or null if not found
                 */
                async function scanWithJsQR(imageFile) {
                    return new Promise((resolve, reject) => {
                        const img = new Image();
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        
                        img.onload = function() {
                            // Use larger canvas for better detection
                            const maxDimension = 1200;
                            let width = img.width;
                            let height = img.height;
                            
                            // Scale down if too large (but not too small)
                            if (width > maxDimension || height > maxDimension) {
                                const scale = maxDimension / Math.max(width, height);
                                width = Math.floor(width * scale);
                                height = Math.floor(height * scale);
                            }
                            
                            canvas.width = width;
                            canvas.height = height;
                            
                            // Draw image with white background (helps with transparent PNGs)
                            ctx.fillStyle = 'white';
                            ctx.fillRect(0, 0, width, height);
                            ctx.drawImage(img, 0, 0, width, height);
                            
                            const imageData = ctx.getImageData(0, 0, width, height);
                            
                            console.log(`[jsQR] Scanning image ${width}x${height}`);
                            
                            // Try scanning with different options
                            let code = window.jsQR(imageData.data, width, height, {
                                inversionAttempts: 'attemptBoth' // Try both normal and inverted
                            });
                            
                            if (code) {
                                console.log('[jsQR] ✓ QR code found!');
                                resolve(code.data);
                                return;
                            }
                            
                            // If not found, try with image enhancement
                            console.log('[jsQR] First attempt failed, trying with enhanced contrast...');
                            
                            // Enhance contrast
                            const enhanced = enhanceImageData(imageData);
                            code = window.jsQR(enhanced.data, width, height, {
                                inversionAttempts: 'attemptBoth'
                            });
                            
                            if (code) {
                                console.log('[jsQR] ✓ QR code found with enhanced contrast!');
                                resolve(code.data);
                                return;
                            }
                            
                            // Try at different scales
                            const scales = [0.5, 1.5, 2.0];
                            for (const scale of scales) {
                                const scaledWidth = Math.floor(img.width * scale);
                                const scaledHeight = Math.floor(img.height * scale);
                                
                                if (scaledWidth < 100 || scaledHeight < 100 || scaledWidth > 2000 || scaledHeight > 2000) {
                                    continue;
                                }
                                
                                canvas.width = scaledWidth;
                                canvas.height = scaledHeight;
                                ctx.fillStyle = 'white';
                                ctx.fillRect(0, 0, scaledWidth, scaledHeight);
                                ctx.drawImage(img, 0, 0, scaledWidth, scaledHeight);
                                
                                const scaledData = ctx.getImageData(0, 0, scaledWidth, scaledHeight);
                                code = window.jsQR(scaledData.data, scaledWidth, scaledHeight, {
                                    inversionAttempts: 'attemptBoth'
                                });
                                
                                if (code) {
                                    console.log(`[jsQR] ✓ QR code found at scale ${scale}!`);
                                    resolve(code.data);
                                    return;
                                }
                            }
                            
                            console.log('[jsQR] ✗ No QR code found after all attempts');
                            resolve(null);
                        };
                        
                        img.onerror = function(err) {
                            console.error('[jsQR] Failed to load image:', err);
                            reject(new Error('Failed to load image'));
                        };
                        
                        // Load image from file
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            img.src = e.target.result;
                        };
                        reader.onerror = function(err) {
                            console.error('[jsQR] FileReader error:', err);
                            reject(new Error('Failed to read file'));
                        };
                        reader.readAsDataURL(imageFile);
                    });
                }
                
                /**
                 * Enhance image data for better QR detection
                 */
                function enhanceImageData(imageData) {
                    const data = new Uint8ClampedArray(imageData.data);
                    
                    // Simple contrast enhancement
                    for (let i = 0; i < data.length; i += 4) {
                        // Convert to grayscale
                        const gray = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114);
                        
                        // Apply threshold for better black/white separation
                        const threshold = 128;
                        const enhanced = gray < threshold ? 0 : 255;
                        
                        data[i] = enhanced;     // R
                        data[i + 1] = enhanced; // G
                        data[i + 2] = enhanced; // B
                        // Keep alpha as is
                    }
                    
                    return new ImageData(data, imageData.width, imageData.height);
                }
                
                /**
                 * Scan QR code using html5-qrcode as fallback
                 */
                async function scanWithHtml5QrCode(imageFile) {
                    return new Promise((resolve, reject) => {
                        try {
                            // Create a temporary element for scanning
                            let tempDiv = document.getElementById('temp-file-scanner');
                            if (!tempDiv) {
                                tempDiv = document.createElement('div');
                                tempDiv.id = 'temp-file-scanner';
                                tempDiv.style.display = 'none';
                                document.body.appendChild(tempDiv);
                            }
                            
                            const scanner = new Html5Qrcode("temp-file-scanner", {
                                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                                verbose: false
                            });
                            
                            scanner.scanFile(imageFile, true)
                                .then(decodedText => {
                                    console.log('[html5-qrcode] ✓ QR code found');
                                    scanner.clear().catch(() => {});
                                    resolve(decodedText);
                                })
                                .catch(err => {
                                    console.log('[html5-qrcode] ✗ No QR code found:', err.message || err);
                                    scanner.clear().catch(() => {});
                                    resolve(null);
                                });
                        } catch (err) {
                            console.error('[html5-qrcode] Scanner error:', err);
                            resolve(null);
                        }
                    });
                }

                // Initialize file upload scanner (called when file mode is selected)
                window.initFileUploadScanner = function() {
                    console.log('Initializing file upload scanner (jsQR + html5-qrcode fallback)');

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

                    newFileInput.addEventListener('change', async function(e) {
                        console.log('File input change event triggered');

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
                        console.log('=== File Upload Scan Started ===');
                        console.log('File:', imageFile.name, '|', imageFile.size, 'bytes |', imageFile.type);

                        // Check if file-qr-reader element exists
                        const fileReaderElement = document.getElementById('file-qr-reader');
                        if (!fileReaderElement) {
                            console.error('file-qr-reader element not found. File upload mode may not be active.');
                            $wire.call('scannerError', 'Scanner not ready. Please select "Upload QR Image" mode first.',
                                'Scanner Error');
                            e.target.value = '';
                            return;
                        }

                        // Show loading feedback with scanner info
                        fileReaderElement.innerHTML =
                            '<div class="flex flex-col items-center justify-center h-full"><span class="loading loading-spinner loading-lg text-primary"></span><p class="text-sm mt-2 text-base-content/70">Scanning with jsQR...</p></div>';

                        let decodedText = null;
                        let scannerUsed = null;
                        
                        try {
                            // Try jsQR first (more reliable for image files)
                            console.log('[Scanner] Attempting jsQR scan...');
                            decodedText = await scanWithJsQR(imageFile);
                            
                            if (decodedText) {
                                scannerUsed = 'jsQR';
                                console.log('[Scanner] ✓ jsQR succeeded!');
                            } else {
                                // Fallback to html5-qrcode
                                console.log('[Scanner] jsQR failed, trying html5-qrcode fallback...');
                                fileReaderElement.innerHTML =
                                    '<div class="flex flex-col items-center justify-center h-full"><span class="loading loading-spinner loading-lg text-primary"></span><p class="text-sm mt-2 text-base-content/70">Trying alternative scanner...</p></div>';
                                
                                decodedText = await scanWithHtml5QrCode(imageFile);
                                
                                if (decodedText) {
                                    scannerUsed = 'html5-qrcode';
                                    console.log('[Scanner] ✓ html5-qrcode fallback succeeded!');
                                }
                            }
                        } catch (err) {
                            console.error('[Scanner] Scanning error:', err);
                        }
                        
                        if (!decodedText) {
                            console.error('❌ All scanners failed to detect QR code');
                            fileReaderElement.innerHTML =
                                '<div class="flex flex-col items-center justify-center h-full text-error"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><p class="text-sm font-semibold">Could not scan QR code</p><p class="text-xs mt-1 text-base-content/60">Ensure the image is clear and contains a valid QR code</p></div>';
                            $wire.call('scannerError',
                                'Could not detect QR code in the image. Please ensure the image contains a clear, valid QR code.',
                                'Scan Failed');
                            e.target.value = '';
                            return;
                        }
                        
                        // Successfully decoded
                        console.log('✓ QR Code decoded using:', scannerUsed);
                        console.log('Decoded text length:', decodedText.length);
                        
                        // Show processing feedback
                        fileReaderElement.innerHTML =
                            '<div class="flex flex-col items-center justify-center h-full text-info"><span class="loading loading-spinner loading-lg mb-2"></span><p class="text-sm">Processing QR code...</p></div>';

                        // Validation result logging
                        console.group('📋 QR Code Validation');
                        console.log('Source: File Upload');
                        console.log('Scanner:', scannerUsed);
                        console.log('Timestamp:', new Date().toISOString());
                        console.log('Data Length:', decodedText.length);
                        console.groupEnd();

                        // Send to backend for processing
                        try {
                            await $wire.call('handleFileUploadScan', decodedText);
                            console.log('✓ Backend processing completed successfully');
                            
                            // Show success feedback
                            fileReaderElement.innerHTML =
                                '<div class="flex flex-col items-center justify-center h-full text-success"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><p class="text-sm">QR code processed!</p></div>';
                            
                            // Reset file input after successful processing
                            setTimeout(() => {
                                e.target.value = '';
                            }, 500);
                        } catch (error) {
                            console.error('❌ Backend processing failed:', error);
                            fileReaderElement.innerHTML =
                                '<div class="flex flex-col items-center justify-center h-full text-error"><svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><p class="text-sm">Processing failed</p></div>';
                            e.target.value = '';
                        }
                    });

                    console.log('File upload scanner initialized (jsQR primary + html5-qrcode fallback)');
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
