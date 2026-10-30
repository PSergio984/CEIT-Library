<div class="p-4 sm:p-6 pb-24">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <x-mary-header title="Bag Counter" subtitle="Scan first — deposits and claims follow the courier" separator>
        <x-slot:actions>
            <x-mary-button wire:click="openScanner" icon="o-qr-code" label="Scan QR" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 space-y-4">
            {{-- Scanned courier --}}
            <x-mary-card title="Courier" subtitle="Depositor at drop-off, claimant at pickup">
                @if ($scannedUserId)
                    <div class="rounded-lg bg-base-200 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold">{{ $scannedUserName }}</div>
                                <div class="text-xs opacity-60">Verified by QR — eligible to deposit / claim</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-mary-badge value="Scanned" class="badge-success" />
                                <x-mary-button wire:click="clearScan" icon="o-x-mark" class="btn-ghost btn-xs" tooltip="Clear" />
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-mary-button wire:click="openDepositModal" icon="o-archive-box-arrow-down" label="New deposit" class="btn-primary btn-sm" />
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse ($this->scannedUserDeposits as $d)
                            <div wire:key="scanned-deposit-{{ $d->id }}" class="flex items-center justify-between rounded-lg bg-base-200 px-3 py-2">
                                <div class="text-sm">
                                    <b>Slot {{ str_pad($d->slot->number, 2, '0', STR_PAD_LEFT) }}</b> — {{ $d->description ?: 'Bag' }}
                                    <div class="text-xs opacity-60">
                                        {{ $d->item_count }} item{{ $d->item_count > 1 ? 's' : '' }} · code <span class="font-mono">{{ $d->claim_code }}</span> · {{ $d->created_at->format('g:i A') }}
                                    </div>
                                </div>
                                <x-mary-button wire:click="claimDeposit({{ $d->id }})" label="Claim" class="btn-primary btn-xs"
                                               wire:confirm="Release this deposit to {{ $scannedUserName }}?" />
                            </div>
                        @empty
                            <div class="text-sm opacity-60">No open deposits for this user.</div>
                        @endforelse
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-base-300 p-6 text-center text-sm opacity-60">
                        Waiting for a user QR…
                    </div>
                @endif
            </x-mary-card>

            {{-- Open deposits ledger --}}
            <x-mary-card title="Open deposits" subtitle="Every bag still on the counter">
                @php
                    $headers = [
                        ['key' => 'slot', 'label' => 'Slot'],
                        ['key' => 'depositor', 'label' => 'Depositor'],
                        ['key' => 'items', 'label' => 'Items'],
                        ['key' => 'since', 'label' => 'Since'],
                    ];
                @endphp
                <x-mary-table :headers="$headers" :rows="$this->openDeposits" striped
                              row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">
                    @scope('cell_slot', $d) <span class="font-mono font-bold">{{ str_pad($d->slot->number, 2, '0', STR_PAD_LEFT) }}</span> @endscope
                    @scope('cell_depositor', $d) {{ $d->user->first_name }} {{ $d->user->last_name }} @endscope
                    @scope('cell_items', $d) {{ $d->item_count }} — {{ $d->description ?: 'Bag' }} @endscope
                    @scope('cell_since', $d) {{ $d->created_at->format('g:i A') }} @endscope
                </x-mary-table>
            </x-mary-card>
        </div>

        <div class="lg:col-span-2 space-y-4">
            {{-- Slot board --}}
            <x-mary-card title="Slots" subtitle="Free slot: click to take out of service — out of service: click to restore">
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($this->bagSlots as $slot)
                        @php $state = $slot->state(); @endphp
                        <button wire:key="slot-{{ $slot->id }}"
                                @if ($state === 'free')
                                    wire:click="toggleSlotService({{ $slot->id }})" wire:confirm="Mark slot {{ $slot->number }} out of service?"
                                @elseif ($state === 'out_of_service')
                                    wire:click="toggleSlotService({{ $slot->id }})" wire:confirm="Put slot {{ $slot->number }} back in service?"
                                @endif
                                class="rounded-md px-2 py-3 text-center text-xs font-mono transition
                                    {{ $state === 'free' ? 'border border-dashed border-base-300 hover:border-error/60 hover:bg-error/5' : '' }}
                                    {{ $state === 'occupied' ? 'bg-primary/15 text-primary' : '' }}
                                    {{ $state === 'out_of_service' ? 'bg-base-300/50 line-through opacity-50 hover:opacity-80' : '' }}">
                            {{ str_pad($slot->number, 2, '0', STR_PAD_LEFT) }}
                        </button>
                    @endforeach
                </div>
                @if ($this->bagSlots->isEmpty())
                    <div class="text-sm opacity-60">No slots configured yet.</div>
                @endif
            </x-mary-card>
        </div>
    </div>

    {{-- ============================ DEPOSIT MODAL ============================ --}}
    <x-mary-modal wire:model="depositModal" title="{{ $issuedCode ? 'Deposit recorded' : 'New deposit' }}">
        @if ($issuedCode)
            <div class="p-4 text-center">
                <div class="text-sm opacity-70">Claim code for {{ $scannedUserName }} — visible on their deposit</div>
                <div class="my-4 font-mono text-5xl font-bold tracking-widest">{{ $issuedCode }}</div>
                <div class="text-xs opacity-60">Share it to let a teammate claim. Nothing physical handed out.</div>
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button label="Done" class="btn-primary" @click="$wire.depositModal = false" />
            </div>
        @else
            <div class="p-4 space-y-4">
                <x-mary-select label="Slot" wire:model="formSlot"
                               :options="$this->availableSlots->map(fn ($s) => ['id' => $s->id, 'name' => 'Slot '.str_pad($s->number, 2, '0', STR_PAD_LEFT)])->toArray()"
                               placeholder="Pick a free slot" />
                <x-mary-input label="Item count" type="number" min="1" wire:model="formItemCount" />
                <x-mary-textarea label="Description (optional)" wire:model="formDescription" placeholder="e.g. 2 boxes, 1 backpack" rows="2" />
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.depositModal = false" />
                <x-mary-button wire:click="submitDeposit" label="Record deposit" class="btn-primary" />
            </div>
        @endif
    </x-mary-modal>

    {{-- ============================ QR SCANNER MODAL ============================ --}}
    <x-mary-modal wire:model="showQrModal" title="Scan courier QR" class="backdrop-blur" box-class="max-w-2xl">
        <div class="space-y-4" x-data="bagQrScanner()" x-init="init()">
            @if ($isProcessingQr)
                <div class="bg-primary/10 border border-primary rounded-lg p-6 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="loading loading-spinner loading-lg text-primary"></div>
                        <p class="font-semibold text-lg">Processing QR Code...</p>
                    </div>
                </div>
            @else
                {{-- File upload --}}
                <div x-show="!cameraMode">
                    <div class="alert alert-info mb-4">
                        <x-mary-icon name="o-information-circle" class="w-6 h-6 shrink-0" />
                        <span class="text-sm">Upload an image containing the QR code or use your camera.</span>
                    </div>

                    <input type="file" accept="image/*" @change="handleFileUpload($event)"
                           class="file-input file-input-bordered w-full mb-4" />

                    <div x-show="uploadError" class="alert alert-error mb-4" x-transition>
                        <span class="text-sm" x-text="uploadError"></span>
                    </div>

                    <button type="button" @click="startCamera()" class="btn btn-outline w-full">
                        <x-mary-icon name="o-camera" class="w-5 h-5" />
                        Use Camera Instead
                    </button>
                </div>

                {{-- Camera scanner --}}
                <div x-show="cameraMode" style="display: none;">
                    <div id="bag-qr-reader" wire:ignore class="w-full rounded-lg overflow-hidden bg-black mb-4" style="min-height: 300px;"></div>
                    <button type="button" @click="stopCamera()" class="btn btn-outline btn-sm w-full">
                        <x-mary-icon name="o-arrow-left" class="w-4 h-4" />
                        Back to File Upload
                    </button>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="closeQrModal" :disabled="$isProcessingQr" />
        </x-slot:actions>
    </x-mary-modal>

    @script
        <script>
            window.bagQrScanner = function () {
                return {
                    cameraMode: false,
                    uploadError: null,
                    html5QrCode: null,

                    init() {
                        Livewire.on('qr-modal-closed', () => this.stopCamera());
                        this.$watch('$wire.showQrModal', (value) => {
                            if (!value) this.stopCamera();
                        });
                    },

                    async handleFileUpload(event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        this.uploadError = null;

                        try {
                            const scanner = new Html5Qrcode('bag-qr-file-reader');
                            const qrText = await scanner.scanFile(file, true);
                            await scanner.clear();
                            await $wire.call('handleScan', qrText);
                        } catch (e) {
                            console.log('QR file scan failed:', e);
                            this.uploadError = 'No QR code found in that image — try a clearer photo.';
                        }

                        event.target.value = '';
                    },

                    async startCamera() {
                        this.cameraMode = true;
                        this.uploadError = null;

                        try {
                            await new Promise((resolve) => setTimeout(resolve, 100));
                            this.html5QrCode = new Html5Qrcode('bag-qr-reader');
                            await this.html5QrCode.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 250, height: 250 } },
                                (decodedText) => {
                                    this.stopCamera();
                                    $wire.call('handleScan', decodedText);
                                },
                                () => {}
                            );
                        } catch (e) {
                            console.log('Camera start failed:', e);
                            this.uploadError = 'Camera unavailable — use file upload instead.';
                            this.cameraMode = false;
                        }
                    },

                    async stopCamera() {
                        if (this.html5QrCode) {
                            try {
                                await this.html5QrCode.stop();
                                this.html5QrCode.clear();
                            } catch (e) {
                                console.log('Camera stop failed:', e);
                            }
                            this.html5QrCode = null;
                        }
                        this.cameraMode = false;
                    },
                };
            };
        </script>
    @endscript

    {{-- Hidden reader target for file-based scans --}}
    <div id="bag-qr-file-reader" wire:ignore class="hidden" style="width:1px;height:1px;"></div>
</div>
