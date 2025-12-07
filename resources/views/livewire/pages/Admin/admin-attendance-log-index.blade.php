<div class="p-6">
    <div class="mb-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div class="flex-1">
                <x-mary-header title="Attendance Logs" subtitle="All library attendance records" separator />
            </div>
            <div class="flex gap-2 md:flex-shrink-0">
                <x-mary-button wire:click="exportPdf" class="btn-primary flex-1 md:flex-none" icon="o-arrow-down-tray">
                    <span class="hidden sm:inline">Export PDF</span>
                    <span class="sm:hidden text-xs">Export PDF</span>
                </x-mary-button>
                <x-mary-button wire:click="openScanner" class="btn-primary flex-1 md:flex-none" icon="o-qr-code">
                    <span class="hidden sm:inline">Scan QR Code</span>
                    <span class="sm:hidden text-xs">Scan QR</span>
                </x-mary-button>
            </div>
        </div>
    </div>

    {{-- Load QR libraries first --}}
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <x-mary-stat
            title="Currently in Library"
            description="Students checked in today"
            value="{{ $this->currentlyInLibrary }}"
            icon="o-user-group"
            class="bg-base-200 p-4 rounded-lg mb-6"
            tooltip-bottom="Students who are currently in the library" />

        <x-mary-stat
            title="Timed Out Today"
            description="Students who left today"
            value="{{ $this->timedOutToday }}"
            icon="o-arrow-right-on-rectangle"
            class="bg-base-200 p-4 rounded-lg mb-6"
            tooltip-bottom="Students who checked out today" />
    </div>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.200ms="search"
                              placeholder="Search by name or librarian..." icon="o-magnifying-glass" />
            </div>

            <div>
                <x-mary-select label="Status" wire:model.live="statusFilter" :options="[
                    ['id' => '', 'name' => 'All Status'],
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'completed', 'name' => 'Completed'],
                ]" option-value="id" option-label="name" />
            </div>

            <div>
                <x-mary-select label="Role" wire:model.live="roleFilter"
                               :options="collect([['id' => '', 'name' => 'All Roles']])->merge($this->roles->map(fn($role) => [
                                   'id' => $role->id,
                                   'name' => str_replace('_', ' ', ucwords(str_replace('_', ' ', $role->name)))
                               ]))"
                               option-value="id" option-label="name" />
            </div>

            <div>
                <x-mary-datetime label="Filter by Date" wire:model.live="selectedDate" type="date"
                                 max="{{ date('Y-m-d') }}" />
            </div>

            <div class="flex items-end">
                <x-mary-button wire:click="clearFilters" class="btn-outline w-full" icon="o-x-mark">
                    Clear Filters
                </x-mary-button>
            </div>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->attendances->count() }} of {{ $this->attendances->total() }} results
    </div>

    {{-- Mobile Card View --}}
    <div class="block lg:hidden space-y-4">
        @foreach ($this->attendances as $attendance)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $attendance['user_name'] }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            <span class="badge {{ $attendance['role_badge_color'] }} badge-sm">{{ $attendance['role_name'] }}</span>
                        </p>
                        <p class="text-base-content/50 font-medium mt-2">Scanned By:</p>
                        <p class="text-sm text-base-content/70">{{ $attendance['scanned_by_name'] }}</p>
                    </div>
                    <span class="badge badge-{{ $attendance['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                        {{ ucfirst($attendance['status']) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-base-content/50 font-medium">Time In</p>
                        @if ($attendance['time_in'])
                            <p class="font-medium">{{ $attendance['time_in']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $attendance['time_in']->format('H:i') }}</p>
                        @else
                            <p class="text-base-content/50">N/A</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium">Time Out</p>
                        @if ($attendance['time_out'])
                            <p class="font-medium">{{ $attendance['time_out']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $attendance['time_out']->format('H:i') }}</p>
                        @else
                            <p class="text-warning font-medium">In Library</p>
                        @endif
                    </div>
                </div>

                @if ($attendance['duration_minutes'] !== null && $attendance['duration_minutes'] >= 0)
                    <div class="mt-3 pt-3 border-t border-base-300">
                        <p class="text-base-content/50 font-medium text-xs">Duration</p>
                        <p class="font-medium">
                            @php
                                $mins = (int)$attendance['duration_minutes'];
                                $hours = floor($mins / 60);
                                $remainingMins = $mins % 60;
                            @endphp
                            @if($mins < 1)
                                <span class="text-warning">< 1m</span>
                            @elseif($hours > 0)
                                {{ $hours }}h {{ $remainingMins }}m
                            @else
                                {{ $mins }}m
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->attendances->links() }}
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden lg:block overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->attendances" :sort-by="$sortBy" with-pagination striped
                      row-class="hover:bg-base-200" header-class="text-base-content bg-base-200" class="w-full min-w-fit table-auto">

            @scope('cell_user_name', $row)
            <div class="font-medium">{{ $row['user_name'] }}</div>
            @endscope

            @scope('cell_role_name', $row)
            <span class="badge {{ $row['role_badge_color'] }} badge-sm">{{ $row['role_name'] }}</span>
            @endscope

            @scope('cell_email', $row)
            <div class="text-sm text-base-content/70">{{ $row['scanned_by_name'] }}</div>
            @endscope

            @scope('cell_time_in', $row)
            <div class="text-sm">
                @if ($row['time_in'])
                    <div>{{ $row['time_in']->format('M d, Y') }}</div>
                    <div class="text-xs text-base-content/50">{{ $row['time_in']->format('H:i') }}</div>
                @else
                    <span class="text-base-content/50">N/A</span>
                @endif
            </div>
            @endscope

            @scope('cell_time_out', $row)
            <div class="text-sm">
                @if ($row['time_out'])
                    <div>{{ $row['time_out']->format('M d, Y') }}</div>
                    <div class="text-xs text-base-content/50">{{ $row['time_out']->format('H:i') }}</div>
                @else
                    <span class="text-warning font-medium">In Library</span>
                @endif
            </div>
            @endscope

            @scope('cell_duration_minutes', $row)
            @if ($row['duration_minutes'] !== null && $row['duration_minutes'] >= 0)
                <div class="text-sm font-medium">
                    @php
                        $mins = (int)$row['duration_minutes'];
                        $hours = floor($mins / 60);
                        $remainingMins = $mins % 60;
                    @endphp
                    @if($mins < 1)
                        <span class="text-warning">< 1m</span>
                    @elseif($hours > 0)
                        {{ $hours }}h {{ $remainingMins }}m
                    @else
                        {{ $mins }}m
                    @endif
                </div>
            @else
                <span class="text-base-content/50">—</span>
            @endif
            @endscope

            @scope('cell_status', $row)
            <span class="badge badge-{{ $row['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                    {{ ucfirst($row['status']) }}
                </span>
            @endscope
        </x-mary-table>
    </div>

    @if ($this->attendances->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No attendance records found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
            <x-mary-button wire:click="clearFilters" class="btn-outline">
                Clear All Filters
            </x-mary-button>
        </div>
    @endif

    {{-- QR Scanner Modal --}}
    <x-mary-modal wire:model="showQrModal" title="Scan QR Code" class="backdrop-blur" box-class="max-w-2xl">
        <div class="space-y-4" x-data="qrScanner()" x-init="init()">
            {{-- Processing Indicator --}}
            @if ($isProcessingQr)
                <div class="bg-primary/10 border border-primary rounded-lg p-6 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="loading loading-spinner loading-lg text-primary"></div>
                        <div>
                            <p class="font-semibold text-lg">Processing QR Code...</p>
                            <p class="text-sm text-base-content/70 mt-1">Please wait while we validate the request</p>
                        </div>
                        <progress class="progress progress-primary w-full max-w-xs"></progress>
                    </div>
                </div>
            @else
                {{-- File Upload (Primary Method) --}}
                <div id="qr-upload-container" x-show="!cameraMode">
                    <div class="alert alert-info mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            class="stroke-current shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Upload an image containing the QR code or use your camera.</span>
                    </div>

                    <input type="file" accept="image/*" @change="handleFileUpload($event)"
                        class="file-input file-input-bordered w-full mb-4" />

                    {{-- Camera Button --}}
                    <button type="button" @click="startCamera()" class="btn btn-outline w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                        Use Camera Instead
                    </button>
                </div>

                {{-- Camera Scanner --}}
                <div x-show="cameraMode" style="display: none;">
                    <div x-show="cameraStatus === 'starting'" class="text-center mb-4">
                        <div class="loading loading-spinner loading-lg text-primary"></div>
                        <p class="mt-2">Starting camera...</p>
                    </div>

                    <div x-show="cameraStatus === 'ready'" class="mb-4">
                        <p class="text-success font-semibold text-center">Camera ready! Point at QR code</p>
                    </div>

                    <div id="qr-reader" wire:ignore class="w-full rounded-lg overflow-hidden bg-black mb-4"></div>

                    {{-- Back to Upload Button --}}
                    <button type="button" @click="stopCamera()" class="btn btn-outline btn-sm w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                        </svg>
                        Back to File Upload
                    </button>
                </div>
            @endif

            @if ($scannedQrData && !$isProcessingQr)
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>QR Code detected successfully!</span>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="closeQrModal" :disabled="$isProcessingQr" />
        </x-slot:actions>
    </x-mary-modal>

    @script
        <script>
            window.qrScanner = function() {
                return {
                    cameraMode: false,
                    cameraStatus: 'stopped',
                    html5QrCode: null,

                    init() {
                        console.log('QR Scanner Alpine component initialized');
                        
                        // Stop camera when modal closes
                        Livewire.on('qr-modal-closed', () => {
                            this.stopCamera();
                        });
                    },

                    async handleFileUpload(event) {
                        const file = event.target.files[0];
                        console.log('File selected:', file?.name, 'Type:', file?.type, 'Size:', file?.size);

                        if (!file) {
                            console.log('No file selected');
                            return;
                        }

                        // Validate file type
                        if (!file.type.startsWith('image/')) {
                            alert('Please select an image file');
                            event.target.value = '';
                            return;
                        }

                        console.log('Setting processing state...');
                        $wire.set('isProcessingQr', true);

                        try {
                            // Try method 1: Html5Qrcode
                            console.log('Trying Html5Qrcode library...');
                            try {
                                const tempDiv = document.createElement('div');
                                tempDiv.id = 'temp-qr-reader';
                                tempDiv.style.display = 'none';
                                document.body.appendChild(tempDiv);

                                const scanner = new Html5Qrcode('temp-qr-reader');
                                const qrText = await scanner.scanFile(file, true);

                                await scanner.clear();
                                document.body.removeChild(tempDiv);

                                console.log('✓ Html5Qrcode succeeded:', qrText);
                                await this.processQrResult(qrText);
                                return;
                            } catch (html5Error) {
                                console.log('✗ Html5Qrcode failed:', html5Error.message);

                                // Clean up
                                const tempDiv = document.getElementById('temp-qr-reader');
                                if (tempDiv) document.body.removeChild(tempDiv);
                            }

                            // Try method 2: jsQR (fallback)
                            console.log('Trying jsQR library as fallback...');
                            if (typeof jsQR !== 'undefined' || typeof window.jsQR !== 'undefined') {
                                const qrText = await this.scanWithJsQR(file);
                                if (qrText) {
                                    console.log('jsQR succeeded:', qrText);
                                    await this.processQrResult(qrText);
                                    return;
                                } else {
                                    console.log('jsQR returned null - no QR code detected');
                                }
                            } else {
                                console.error('jsQR library is not loaded!');
                            }

                            // Both methods failed
                            throw new Error(
                                'QR code could not be detected. Please ensure the image is clear and contains a valid QR code.'
                            );

                        } catch (error) {
                            console.error('All QR scanning methods failed:', error);
                            $wire.set('isProcessingQr', false);

                            alert('Could not read QR code from this image.\n\n' +
                                'Please try:\n' +
                                '• Taking a clearer, higher resolution photo\n' +
                                '• Ensuring good lighting\n' +
                                '• Getting closer to the QR code\n' +
                                '• Using the camera option instead\n\n' +
                                'Error: ' + error.message);
                        }

                        event.target.value = '';
                    },

                    async scanWithJsQR(file) {
                        console.log('scanWithJsQR called with file:', file.name);
                        return new Promise((resolve) => {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                const img = new Image();
                                img.onload = () => {
                                    const jsQRFunc = window.jsQR || jsQR;

                                    // Try multiple scales for better detection
                                    const scales = [1, 0.5, 1.5, 2, 0.25];

                                    for (const scale of scales) {
                                        const canvas = document.createElement('canvas');
                                        const ctx = canvas.getContext('2d');

                                        canvas.width = img.width * scale;
                                        canvas.height = img.height * scale;

                                        ctx.imageSmoothingEnabled = true;
                                        ctx.imageSmoothingQuality = 'high';
                                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                                        console.log(
                                            `Attempting jsQR scan at scale ${scale}: ${canvas.width}x${canvas.height}`
                                        );

                                        const code = jsQRFunc(imageData.data, imageData.width, imageData.height, {
                                            inversionAttempts: 'attemptBoth',
                                        });

                                        if (code) {
                                            console.log('✓ jsQR detected code at scale', scale, ':', code.data);
                                            resolve(code.data);
                                            return;
                                        }
                                    }

                                    console.log('✗ jsQR could not detect QR code at any scale');
                                    resolve(null);
                                };
                                img.onerror = (err) => {
                                    console.error('Image load error:', err);
                                    resolve(null);
                                };
                                img.src = e.target.result;
                            };
                            reader.onerror = (err) => {
                                console.error('File read error:', err);
                                resolve(null);
                            };
                            reader.readAsDataURL(file);
                        });
                    },

                    async processQrResult(qrText) {
                        console.log('Processing QR result:', qrText);
                        const result = await $wire.call('processScannedQr', qrText);
                        console.log('Process result:', result);

                        if (!result?.found) {
                            console.log('QR not found or invalid, hiding processing state');
                            $wire.set('isProcessingQr', false);
                        } else {
                            // Close modal after successful scan
                            setTimeout(() => {
                                $wire.call('closeQrModal');
                            }, 1500);
                        }
                    },

                    async startCamera() {
                        console.log('Starting camera...');

                        try {
                            const devices = await navigator.mediaDevices.enumerateDevices();
                            const cameras = devices.filter(d => d.kind === 'videoinput');
                            console.log('Cameras found:', cameras.length);

                            if (cameras.length === 0) {
                                alert('No camera found. Please use file upload.');
                                return;
                            }

                            this.cameraMode = true;
                            this.cameraStatus = 'starting';

                            await this.$nextTick();

                            console.log('Initializing Html5Qrcode...');
                            this.html5QrCode = new Html5Qrcode('qr-reader');

                            await this.html5QrCode.start({
                                    facingMode: 'environment'
                                }, {
                                    fps: 20,
                                    qrbox: function(viewfinderWidth, viewfinderHeight) {
                                        let minEdgePercentage = 0.9;
                                        let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                                        let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                                        return {
                                            width: qrboxSize,
                                            height: qrboxSize
                                        };
                                    },
                                    aspectRatio: 1.0,
                                    disableFlip: false,
                                },
                                async (decodedText) => {
                                        console.log('QR detected from camera:', decodedText);

                                        // Prevent multiple scans while processing
                                        if ($wire.get('isProcessingQr')) {
                                            console.log('Already processing a QR code, skipping...');
                                            return;
                                        }

                                        $wire.set('isProcessingQr', true);
                                        const result = await $wire.call('processScannedQr', decodedText);
                                        console.log('Camera QR result:', result);

                                        // Stop camera after successful scan
                                        if (result?.found) {
                                            this.stopCamera();
                                            setTimeout(() => {
                                                $wire.call('closeQrModal');
                                            }, 1500);
                                        } else {
                                            $wire.set('isProcessingQr', false);
                                        }
                                    },
                                    (errorMessage) => {
                                        // Error callback for scanning errors (can be ignored for continuous scanning)
                                    }
                            );

                            this.cameraStatus = 'ready';
                            console.log('Camera started successfully');

                        } catch (error) {
                            console.error('Camera error:', error);
                            alert('Failed to start camera: ' + error.message);
                            this.stopCamera();
                        }
                    },

                    stopCamera() {
                        console.log('Stopping camera...');

                        if (this.html5QrCode) {
                            this.html5QrCode.stop()
                                .then(() => {
                                    console.log('Camera stopped');
                                    this.html5QrCode.clear();
                                    this.html5QrCode = null;
                                })
                                .catch(err => console.warn('Stop error:', err));
                        }

                        this.cameraMode = false;
                        this.cameraStatus = 'stopped';
                    }
                }
            }
        </script>
    @endscript
</div>
