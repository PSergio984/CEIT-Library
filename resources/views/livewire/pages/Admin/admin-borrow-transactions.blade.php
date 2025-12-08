@php
    // Livewire Lazy Loading: If this is being rendered as a placeholder, show loading skeleton
    if (isset($placeholder) && $placeholder) {
        echo view('components.loading-placeholder');
        return;
    }
@endphp

<div class="p-6">
    {{-- Load QR libraries first --}}
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

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
                    placeholder="Search by name, title..." icon="o-magnifying-glass" />
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

    <div class="block lg:hidden space-y-4 relative">
        {{-- Loading overlay for mobile view --}}
        <div wire:loading.flex 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage"
            class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg">
            <div class="flex flex-col items-center gap-2">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <p class="text-base-content font-medium">Updating transactions...</p>
            </div>
        </div>
        
        @foreach ($this->transactions as $transaction)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $transaction['user_name'] }}</h3>
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
                    @if ($this->canEdit)
                        <x-mary-button wire:click="openEditModal({{ $transaction['id'] }})" class="btn-sm btn-outline"
                            icon="o-pencil">
                            Edit
                        </x-mary-button>
                    @else
                        <span class="text-xs text-base-content/50">View Only</span>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->transactions->links() }}
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto rounded-lg border border-base-300 relative">
        {{-- Loading overlay for desktop table --}}
        <div wire:loading.flex 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage"
            class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg">
            <div class="flex flex-col items-center gap-2">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <p class="text-base-content font-medium">Updating transactions...</p>
            </div>
        </div>
        
        <x-mary-table :headers="$headers" :rows="$this->transactions" :sort-by="$sortBy" with-pagination striped
            header-class="text-base-content bg-base-200" class="w-full table-auto" row-class="hover:bg-base-200">
            @scope('cell_user_name', $row)
                <div class="font-medium">{{ $row['user_name'] }}</div>
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
                <div class="flex items-center justify-center">
                    @if ($this->canEdit)
                        <button wire:click="openEditModal({{ $row['id'] }})"
                            class="btn btn-sm btn-square btn-ghost tooltip tooltip-left" data-tip="Edit Transaction">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </button>
                    @else
                        <span class="text-xs text-base-content/50">View</span>
                    @endif
                </div>
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

    {{-- Borrow Confirmation Modal --}}
    <x-mary-modal wire:model="showConfirmBorrowModal" title="Confirm Borrow Request" persistent class="backdrop-blur"
        box-class="max-w-3xl">
        @if (!empty($pendingBorrowData))
            <div class="space-y-6">
                {{-- User Information --}}
                <div class="bg-base-200 rounded-lg p-4">
                    <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Student Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <p class="text-sm text-base-content/60">Name</p>
                            <p class="font-medium">{{ $pendingBorrowData['user_name'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Paper Information --}}
                <div class="bg-base-200 rounded-lg p-4">
                    <h3 class="font-semibold text-lg mb-3 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                        Paper Details
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-base-content/60">Title</p>
                            <p class="font-medium">{{ $pendingBorrowData['title'] ?? 'N/A' }}</p>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <p class="text-sm text-base-content/60">Catalog Code</p>
                                <p class="font-medium">
                                    <span
                                        class="badge badge-primary">{{ $pendingBorrowData['catalog_code'] ?? 'N/A' }}</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Copy Number</p>
                                <p class="font-medium">
                                    <span class="badge badge-info">Copy
                                        #{{ $pendingBorrowData['copy_number'] ?? 'N/A' }}</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Type</p>
                                <p class="font-medium">{{ $pendingBorrowData['paper_type'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Year</p>
                                <p class="font-medium">{{ $pendingBorrowData['publication_year'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-base-content/60">Department</p>
                            <p class="font-medium">{{ $pendingBorrowData['department'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Notes Field --}}
                <div>
                    <x-mary-textarea label="Notes (Optional)" wire:model="borrowNotes"
                        placeholder="Add any notes about this transaction..." rows="3" />
                </div>

                {{-- Warning --}}
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Confirming this will mark <strong>Copy
                            #{{ $pendingBorrowData['copy_number'] ?? 'N/A' }}</strong> as <strong>Unavailable</strong>
                        and create a borrow transaction.</span>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeConfirmBorrowModal" />
            <x-mary-button label="Confirm Borrow" wire:click="confirmBorrow" class="btn-success"
                icon="o-check-circle">
            </x-mary-button>
        </x-slot:actions>
    </x-mary-modal>

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
                            console.log('jsQR available?', typeof jsQR !== 'undefined', 'window.jsQR?', typeof window
                                .jsQR !== 'undefined');

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

                                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas
                                            .height);

                                        console.log(
                                            `Attempting jsQR scan at scale ${scale}: ${canvas.width}x${canvas.height}`
                                        );

                                        const code = jsQRFunc(imageData.data, imageData.width, imageData
                                            .height, {
                                                inversionAttempts: 'attemptBoth',
                                            });

                                        if (code) {
                                            console.log('✓ jsQR detected code at scale', scale, ':',
                                                code.data);
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
                            console.log('QR not found in database, hiding processing state');
                            $wire.set('isProcessingQr', false);
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
                                    fps: 20, // Scan 20 times per second
                                    qrbox: function(viewfinderWidth, viewfinderHeight) {
                                        // Make scan box 90% of the smaller dimension
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

                                        // Don't stop camera - allow continuous scanning for borrow/return workflow
                                        // The camera will keep running so you can scan again to return the book
                                        if (!result?.found) {
                                            $wire.set('isProcessingQr', false);
                                        }
                                        // If result.action === 'returned', processing flag is cleared in backend
                                        // If result.action === 'borrow_prepared', modal opens and processing continues
                                    },
                                    (errorMessage) => {
                                        // Error callback for scanning errors (can be ignored for continuous scanning)
                                        // console.log('Scan error:', errorMessage);
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
