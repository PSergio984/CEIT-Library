<div class="p-6">
    <x-mary-header title="Borrow Transactions" subtitle="all borrow transactions" separator>
        <x-slot:actions>
            <x-mary-button wire:click="openQrModal" class="btn-primary" icon="o-qr-code">
                Scan QR Code
            </x-mary-button>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, email, title..." icon="o-magnifying-glass" />
            </div>

            <div>
                <x-mary-select label="Paper Type" wire:model.live="paperTypeFilter" :options="collect($this->paperTypes)->map(fn($type) => ['id' => $type, 'name' => $type])"
                    placeholder="All Types" option-value="id" option-label="name" />
            </div>

            <div>
                <x-mary-select label="Status" wire:model.live="statusFilter" :options="[
                    ['id' => '', 'name' => 'All Status'],
                    ['id' => 'started', 'name' => 'Started'],
                    ['id' => 'completed', 'name' => 'Completed'],
                ]" option-value="id"
                    option-label="name" />
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
        Showing {{ $this->transactions->count() }} of {{ $this->transactions->total() }} results
    </div>

    <div class="block lg:hidden space-y-4">
        @foreach ($this->transactions as $transaction)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $transaction['user_name'] }}</h3>
                        <p class="text-sm text-base-content/70">
                            {{ $transaction['user']?->email ?? 'N/A' }}</p>
                    </div>
                    <span
                        class="badge badge-{{ $transaction['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                        {{ ucfirst($transaction['status']) }}
                    </span>
                </div>

                <div class="mb-3">
                    <p class="font-medium text-sm mb-1" title="{{ $transaction['title'] }}">
                        {{ Str::limit($transaction['title'], 60) }}
                    </p>
                    <span class="badge badge-outline badge-xs">{{ $transaction['paper_type'] }}</span>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3 text-xs">
                    <div>
                        <p class="text-base-content/50 font-medium">Time In</p>
                        @if ($transaction['time_in'])
                            <p class="font-medium">{{ $transaction['time_in']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $transaction['time_in']->format('H:i') }}</p>
                        @else
                            <p class="text-base-content/50">N/A</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium">Time Out</p>
                        @if ($transaction['time_out'])
                            <p class="font-medium">{{ $transaction['time_out']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $transaction['time_out']->format('H:i') }}</p>
                        @else
                            <p class="text-warning font-medium">Active</p>
                        @endif
                    </div>
                </div>

                @if ($transaction['notes'] && $transaction['notes'] !== 'N/A')
                    <div class="mb-3">
                        <p class="text-base-content/50 font-medium text-xs mb-1">Notes</p>
                        <p class="text-sm line-clamp-2">{{ $transaction['notes'] }}</p>
                    </div>
                @endif

                <div class="flex justify-end mt-3">
                    <x-mary-button wire:click="openEditModal({{ $transaction['id'] }})" class="btn-sm btn-outline"
                        icon="o-pencil">
                        Edit
                    </x-mary-button>
                </div>
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->transactions->links() }}
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->transactions" :sort-by="$sortBy" with-pagination striped
            header-class="text-base-content bg-base-200" class="w-full min-w-fit table-auto">
            @scope('cell_user_name', $row)
                <div class="font-medium">{{ $row['user_name'] }}</div>
            @endscope

            @scope('cell_email', $row)
                <div class="text-sm text-base-content/70">{{ $row['email'] }}</div>
            @endscope

            @scope('cell_title', $row)
                <div class="max-w-64 truncate" title="{{ $row['title'] }}">
                    {{ $row['title'] }}
                </div>
            @endscope

            @scope('cell_paper_type', $row)
                <span class="">{{ $row['paper_type'] }}</span>
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
                        <span class="text-warning">Active</span>
                    @endif
                </div>
            @endscope

            @scope('cell_status', $row)
                <span class="badge badge-{{ $row['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                    {{ ucfirst($row['status']) }}
                </span>
            @endscope

            @scope('cell_notes', $row)
                <div class="min-w-24 max-w-32 text-sm" title="{{ $row['notes'] }}">
                    @if ($row['notes'] && $row['notes'] !== 'N/A')
                        <span class="line-clamp-2">{{ $row['notes'] }}</span>
                    @else
                        <span class="text-base-content/50 italic">No notes</span>
                    @endif
                </div>
            @endscope

            @scope('cell_actions', $row)
                <x-mary-button wire:click="openEditModal({{ $row['id'] }})" class="btn-xs btn-outline" icon="o-pencil">
                    Edit
                </x-mary-button>
            @endscope
        </x-mary-table>
    </div>

    @if ($this->transactions->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No transactions found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
            <x-mary-button wire:click="clearFilters" class="btn-outline">
                Clear All Filters
            </x-mary-button>
        </div>
    @endif

    {{-- Edit Transaction Modal --}}
    <x-mary-modal wire:model="showEditModal" title="Edit Transaction" persistent class="backdrop-blur">
        <div class="space-y-4">
            <div>
                <x-mary-select label="Status" wire:model="editStatus" :options="[['id' => 'started', 'name' => 'Started'], ['id' => 'completed', 'name' => 'Completed']]" option-value="id"
                    option-label="name" required />
            </div>

            <div>
                <x-mary-datetime label="Time Out" wire:model="editTimeOut" type="datetime-local"
                    hint="Leave empty for active transactions" />
            </div>

            @if ($editStatus === 'completed' && empty($editTimeOut))
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Time Out is required when status is completed</span>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeEditModal" />
            <x-mary-button label="Save Changes" wire:click="saveTransaction" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- QR Scanner Modal --}}
    <x-mary-modal wire:model="showQrModal" title="Scan QR Code" class="backdrop-blur" box-class="max-w-2xl">
        <div class="space-y-4">
            {{-- Camera Scanner --}}
            <div id="qr-scanner-container">
                <div id="camera-status" class="text-center mb-4">
                    <div class="loading loading-spinner loading-lg text-primary"></div>
                    <p class="mt-2">Initializing camera...</p>
                </div>
                <div id="qr-reader" class="w-full rounded-lg overflow-hidden bg-black"></div>
            </div>

            {{-- Fallback File Upload --}}
            <div id="qr-upload-container" class="hidden">
                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>No camera found. Please upload an image containing the QR code.</span>
                </div>
                <input type="file" accept="image/*" id="qr-file-input"
                    class="file-input file-input-bordered w-full" />
            </div>

            @if ($scannedQrData)
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>QR Code detected: {{ $scannedQrData }}</span>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="closeQrModal" />
        </x-slot:actions>
    </x-mary-modal>

    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"
            integrity="sha512-r6rDA7W6ZeQhvl8S7yRVQUKVHdexq+GAlNkNNqVC7YyIV+NwqCTJe2hDWCiffTyRNOeGEzRRJ9ifvRm/HCzGYg=="
            crossorigin="anonymous"></script>
        <script>
            let html5QrCode = null;
            let fileInputInitialized = false;
            let cameraStarted = false;

            document.addEventListener('livewire:initialized', () => {
                Livewire.on('qr-modal-opened', () => setTimeout(initializeQrScanner, 100));
                Livewire.on('qr-modal-closed', cleanupScanner);
            });

            /* 1️⃣  runs as soon as the modal opens */
            async function initializeQrScanner() {
                const statusEl = document.getElementById('camera-status');
                const readerEl = document.getElementById('qr-reader');
                const uploadEl = document.getElementById('qr-upload-container');

                if (!statusEl || !readerEl || !uploadEl) return;

                try {
                    /* camera hardware? */
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const camList = devices.filter(d => d.kind === 'videoinput');
                    if (camList.length === 0) throw new Error('No camera');

                    // camera exists — start immediately
                    statusEl.innerHTML =
                        '<div class="text-sm text-base-content/70">Camera detected, starting...</div>';

                    readerEl.style.display = 'block';
                    uploadEl.classList.add('hidden');
                    startCameraOnUserClick();

                } catch (err) {
                    /* no camera – show fallback immediately */
                    console.warn('Camera unavailable:', err);
                    showUploadFallback();
                }
            }


            /* 2️⃣  user clicked the fallback – start real camera */
            async function startCameraOnUserClick() {
                if (cameraStarted) return;
                cameraStarted = true;

                const statusEl = document.getElementById('camera-status');
                const readerEl = document.getElementById('qr-reader');
                const uploadEl = document.getElementById('qr-upload-container');

                statusEl.innerHTML =
                    '<div class="loading loading-spinner loading-lg text-primary"></div>' +
                    '<p class="mt-2">Starting camera…</p>';
                readerEl.style.display = 'block'; /* reveal preview */
                uploadEl.classList.add('hidden'); /* hide fallback while camera starts */

                try {
                    html5QrCode = new Html5Qrcode('qr-reader');
                    await html5QrCode.start({
                            facingMode: 'environment'
                        }, {
                            fps: 10,
                            qrbox: {
                                width: 250,
                                height: 250
                            },
                            aspectRatio: 1.0
                        },
                        decodedText => {
                            console.log('QR:', decodedText);
                            cleanupScanner();
                            @this.call('processScannedQr', decodedText);
                        },
                        /* ignore parse errors */
                        () => {}
                    );
                    statusEl.innerHTML =
                        '<p class="text-success font-semibold">Camera ready!  Point at QR code</p>';
                } catch (err) {
                    console.error('Camera start failed:', err);
                    showUploadFallback(); /* back to fallback */
                }
            }

            /* 3️⃣  file-upload fallback (unchanged) */
            function showUploadFallback() {
                const statusEl = document.getElementById('camera-status');
                const readerEl = document.getElementById('qr-reader');
                const uploadEl = document.getElementById('qr-upload-container');

                if (statusEl) {
                    statusEl.innerHTML =
                        '<div class="alert alert-info mb-4">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" ' +
                        'class="stroke-current shrink-0 w-6 h-6">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" ' +
                        'd="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                        '<span>No camera found.  Please upload an image containing the QR code.</span>' +
                        '</div>';
                }
                if (readerEl) readerEl.style.display = 'none';
                if (uploadEl) uploadEl.classList.remove('hidden');

                if (!fileInputInitialized) {
                    document.getElementById('qr-file-input')?.addEventListener('change', async e => {
                        const file = e.target.files[0];
                        if (!file) return;
                        try {
                            const temp = new Html5Qrcode('qr-reader');
                            const text = await temp.scanFile(file, true);
                            @this.call('processScannedQr', text);
                        } catch {
                            alert('Could not read QR code from this image.');
                        }
                    });
                    fileInputInitialized = true;
                }
            }

            /* 4️⃣  tidy up */
            function cleanupScanner() {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
                    html5QrCode = null;
                }
                cameraStarted = false;
            }
        </script>
    @endpush
</div>
