{{-- PROTOTYPE — throwaway. Three variants of the bag counter, switchable via ?variant=A|B|C.
     Question: what should the counter deposit/claim flow look like? See issue #69. --}}
<div class="p-4 sm:p-6 pb-24">

    <div class="mb-4 flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm">
        <x-mary-icon name="o-beaker" class="w-4 h-4" />
        <span><b>PROTOTYPE</b> — mock data, nothing persists. Variant <b>{{ $variant }}</b>.</span>
    </div>

    {{-- ============================ VARIANT A: Scan command deck ============================ --}}
    @if ($variant === 'A')
        <x-mary-header title="Bag Counter" subtitle="Scan first — deposits, claims and access follow the QR" separator />

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 space-y-4">
                <x-mary-card title="Scanner" subtitle="Simulate the student's QR scan">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($users as $key => $name)
                            <x-mary-button wire:click="simulateScan('{{ $key }}')" label="{{ $name }}"
                                           class="btn-sm {{ $scannedUser === $key ? 'btn-primary' : 'btn-outline' }}" />
                        @endforeach
                    </div>

                    @if ($scannedUser)
                        <div class="mt-4 rounded-lg bg-base-200 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold">{{ $users[$scannedUser] }}</div>
                                    <div class="text-xs opacity-60">Verified by QR — eligible to deposit / claim / access</div>
                                </div>
                                <x-mary-badge value="Scanned" class="badge-success" />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-mary-button wire:click="openDepositModal" icon="o-archive-box-arrow-down" label="New deposit" class="btn-primary btn-sm" />
                                <x-mary-button wire:click="openClaimModal" icon="o-archive-box-x-mark" label="Claim / temp access" class="btn-secondary btn-sm" />
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-lg border border-dashed border-base-300 p-6 text-center text-sm opacity-60">
                            Waiting for a student QR…
                        </div>
                    @endif
                </x-mary-card>

                <x-mary-card title="Claim by code" subtitle="A teammate brings the depositor's 6-digit code">
                    <div class="flex gap-2">
                        <x-mary-input wire:model="claimCodeInput" placeholder="6-digit claim code" class="input-sm w-48" />
                        <x-mary-button wire:click="lookupCode" label="Unlock" class="btn-primary btn-sm" />
                    </div>
                </x-mary-card>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <x-mary-card title="Slots">
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($slots as $n => $state)
                            <div class="rounded-md px-2 py-2 text-center text-xs font-mono
                                {{ $state === 'free' ? 'border border-dashed border-base-300' : '' }}
                                {{ $state === 'occupied' ? 'bg-primary/15 text-primary' : '' }}
                                {{ $state === 'unclaimed' ? 'bg-error/15 text-error' : '' }}
                                {{ $state === 'out_of_service' ? 'bg-base-300/50 line-through opacity-50' : '' }}">
                                {{ str_pad($n, 2, '0', STR_PAD_LEFT) }}
                            </div>
                        @endforeach
                    </div>
                </x-mary-card>

                <x-mary-card title="Unclaimed">
                    @forelse (array_filter($deposits, fn ($d) => $d['unclaimed'] && $d['status'] === 'open') as $d)
                        <div class="flex items-center justify-between rounded-lg bg-error/10 px-3 py-2 text-sm">
                            <span>Slot {{ $d['slot'] }} — {{ $d['depositor'] }} · {{ $d['deposited_at'] }}</span>
                            <x-mary-button wire:click="openRescue({{ $d['id'] }})" label="Rescue" class="btn-error btn-xs" />
                        </div>
                    @empty
                        <div class="text-sm opacity-60">Nothing past closing.</div>
                    @endforelse
                </x-mary-card>
            </div>
        </div>
    @endif

    {{-- ============================ VARIANT B: Slot board ============================ --}}
    @if ($variant === 'B')
        <x-mary-header title="Bag Counter" subtitle="The slot wall is the page — click a bin" separator>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <x-mary-input wire:model="claimCodeInput" placeholder="6-digit code" class="input-sm w-36" />
                    <x-mary-button wire:click="lookupCode" label="Unlock" class="btn-primary btn-sm" />
                </div>
            </x-slot:actions>
        </x-mary-header>

        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="opacity-60">Scan:</span>
            @foreach ($users as $key => $name)
                <x-mary-button wire:click="simulateScan('{{ $key }}')" label="{{ strtok($name, ' ') }}"
                               class="btn-xs {{ $scannedUser === $key ? 'btn-primary' : 'btn-ghost' }}" />
            @endforeach
            @if ($scannedUser)
                <x-mary-badge value="{{ $users[$scannedUser] }} on hand" class="badge-success ml-2" />
                <x-mary-button wire:click="openDepositModal" label="+ deposit" class="btn-primary btn-xs" />
                <x-mary-button wire:click="openClaimModal" label="claim / access" class="btn-secondary btn-xs" />
            @endif
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach ($slots as $n => $state)
                @php $dep = collect($deposits)->firstWhere(fn ($d) => $d['status'] === 'open' && $d['slot'] === $n); @endphp
                <button
                    @if ($dep) wire:click="openDetail({{ $dep['id'] }})" @else wire:click="$js('')" @endif
                    class="aspect-square rounded-xl border-2 p-3 text-left transition hover:scale-[1.02] flex flex-col
                        {{ $state === 'free' ? 'border-dashed border-base-300 text-base-content/40' : '' }}
                        {{ $state === 'occupied' ? 'border-primary/60 bg-primary/10' : '' }}
                        {{ $state === 'unclaimed' ? 'border-error bg-error/10' : '' }}
                        {{ $state === 'out_of_service' ? 'border-base-300 bg-base-300/40 text-base-content/30' : '' }}">
                    <span class="font-mono text-2xl font-bold">{{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</span>
                    @if ($dep)
                        <span class="mt-auto text-xs leading-tight">
                            <b>{{ $dep['depositor'] }}</b><br>{{ $dep['item_count'] }} item{{ $dep['item_count'] > 1 ? 's' : '' }}
                            @if ($dep['unclaimed']) <span class="badge badge-error badge-xs mt-1">unclaimed</span> @endif
                        </span>
                    @elseif ($state === 'out_of_service')
                        <span class="mt-auto text-xs">out of service</span>
                    @else
                        <span class="mt-auto text-xs">free</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    {{-- ============================ VARIANT C: Ledger ============================ --}}
    @if ($variant === 'C')
        @php
            $openCount = count(array_filter($deposits, fn ($d) => $d['status'] === 'open'));
            $occupied = count(array_filter($slots, fn ($s) => $s === 'occupied' || $s === 'unclaimed'));
            $unclaimed = count(array_filter($deposits, fn ($d) => $d['unclaimed'] && $d['status'] === 'open'));
        @endphp
        <x-mary-header title="Bag Counter" subtitle="Open deposits as a working ledger" separator />

        <div class="grid grid-cols-3 gap-4 mb-4">
            <x-mary-stat title="Open deposits" value="{{ $openCount }}" icon="o-archive-box" class="bg-base-200 p-4 rounded-lg" />
            <x-mary-stat title="Slots in use" value="{{ $occupied }}/{{ count($slots) }}" icon="o-squares-2x2" class="bg-base-200 p-4 rounded-lg" />
            <x-mary-stat title="Unclaimed" value="{{ $unclaimed }}" icon="o-exclamation-triangle" class="bg-base-200 p-4 rounded-lg" />
        </div>

        <x-mary-card>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-sm opacity-60">Scan:</span>
                @foreach ($users as $key => $name)
                    <x-mary-button wire:click="simulateScan('{{ $key }}')" label="{{ strtok($name, ' ') }}"
                                   class="btn-xs {{ $scannedUser === $key ? 'btn-primary' : 'btn-ghost' }}" />
                @endforeach
                <div class="mx-2 h-6 w-px bg-base-300"></div>
                <x-mary-input wire:model="claimCodeInput" placeholder="claim code" class="input-sm w-32" />
                <x-mary-button wire:click="lookupCode" label="Unlock" class="btn-primary btn-sm" />
                @if ($scannedUser)
                    <x-mary-button wire:click="openDepositModal" icon="o-plus" label="Deposit for {{ strtok($users[$scannedUser], ' ') }}" class="btn-primary btn-sm ml-auto" />
                @endif
            </div>

            @php
                $headers = [
                    ['key' => 'slot', 'label' => 'Slot'],
                    ['key' => 'depositor', 'label' => 'Depositor'],
                    ['key' => 'items', 'label' => 'Items'],
                    ['key' => 'deposited_at', 'label' => 'Since'],
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'flags', 'label' => ''],
                    ['key' => 'actions', 'label' => 'Actions'],
                ];
            @endphp
            <x-mary-table :headers="$headers" :rows="$this->openDeposits()">
                @scope('cell_slot', $d) <span class="font-mono font-bold">{{ str_pad($d['slot'], 2, '0', STR_PAD_LEFT) }}</span> @endscope
                @scope('cell_items', $d) {{ $d['item_count'] }} — {{ $d['description'] }} @if ($d['photo']) <x-mary-icon name="o-camera" class="w-3 h-3 opacity-50" /> @endif @endscope
                @scope('cell_code', $d) <span class="font-mono">{{ $d['user_key'] === $scannedUser ? $d['code'] : '••••••' }}</span> @endscope
                @scope('cell_flags', $d) @if ($d['unclaimed']) <x-mary-badge value="unclaimed" class="badge-error badge-sm" /> @endif @endscope
                @scope('cell_actions', $d)
                    <div class="flex gap-1">
                        <x-mary-button wire:click="claim({{ $d['id'] }})" label="Claim" class="btn-primary btn-xs" />
                        <x-mary-button wire:click="tempAccess({{ $d['id'] }})" label="Temp" class="btn-ghost btn-xs" />
                        <x-mary-button wire:click="openRescue({{ $d['id'] }})" label="Rescue" class="btn-ghost btn-xs" />
                    </div>
                @endscope
            </x-mary-table>
        </x-mary-card>
    @endif

    {{-- ============================ SHARED MODALS ============================ --}}

    <x-mary-modal wire:model="depositModal" title="{{ $issuedCode ? 'Deposit recorded' : 'New deposit' }}">
        @if ($issuedCode)
            <div class="p-4 text-center">
                <div class="text-sm opacity-70">Claim code for {{ $users[$scannedUser] }} — visible in their app</div>
                <div class="my-4 font-mono text-5xl font-bold tracking-widest">{{ $issuedCode }}</div>
                <div class="text-xs opacity-60">Share it to let a teammate claim. Nothing physical handed out.</div>
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button label="Done" class="btn-primary" @click="$wire.depositModal = false" />
            </div>
        @else
            <div class="p-4 space-y-4">
                <x-mary-select label="Slot" wire:model="formSlot" :options="collect($this->freeSlots())->map(fn ($n) => ['id' => $n, 'name' => 'Slot '.str_pad($n, 2, '0', STR_PAD_LEFT)])->toArray()" />
                <x-mary-input label="Item count" type="number" min="1" wire:model="formItemCount" />
                <x-mary-textarea label="Description" wire:model="formDescription" placeholder="e.g. 2 boxes, 1 backpack" rows="2" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="formPhoto" class="checkbox checkbox-sm" /> Photo taken (optional)</label>
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.depositModal = false" />
                <x-mary-button wire:click="submitDeposit" label="Record deposit" class="btn-primary" />
            </div>
        @endif
    </x-mary-modal>

    <x-mary-modal wire:model="claimModal" title="Claim / temp access — {{ $scannedUser ? $users[$scannedUser] : '' }}">
        <div class="p-4 space-y-3">
            @forelse ($this->entitledDeposits() as $d)
                <div class="flex items-center justify-between rounded-lg bg-base-200 px-3 py-2">
                    <div class="text-sm">
                        <b>Slot {{ $d['slot'] }}</b> — {{ $d['description'] }}
                        <div class="text-xs opacity-60">{{ $d['depositor'] }} · {{ $d['deposited_at'] }}@if ($d['user_key'] !== $scannedUser) · via code @endif</div>
                    </div>
                    <div class="flex gap-1">
                        <x-mary-button wire:click="claim({{ $d['id'] }}, '{{ $d['user_key'] === $scannedUser ? 'owner_scan' : 'claim_code' }}')" label="Claim" class="btn-primary btn-xs" />
                        <x-mary-button wire:click="tempAccess({{ $d['id'] }})" label="Temp" class="btn-ghost btn-xs" />
                    </div>
                </div>
            @empty
                <div class="text-sm opacity-60">No deposits this scan can claim. Enter a code to unlock a teammate's.</div>
            @endforelse

            <div class="flex gap-2 pt-2">
                <x-mary-input wire:model="claimCodeInput" placeholder="6-digit claim code" class="input-sm w-48" />
                <x-mary-button wire:click="lookupCode" label="Unlock" class="btn-primary btn-sm" />
            </div>
        </div>
    </x-mary-modal>

    <x-mary-modal wire:model="rescueModal" title="Rescue lookup — no QR, no code">
        @php $r = $this->depositById($rescueDepositId); @endphp
        @if ($r)
            <div class="p-4 space-y-3">
                <div class="rounded-lg bg-warning/10 border border-warning/30 px-3 py-2 text-sm">
                    Slot {{ $r['slot'] }} — <b>{{ $r['depositor'] }}</b> · {{ $r['description'] }} · deposited {{ $r['deposited_at'] }}
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="rescueIdVerified" class="checkbox checkbox-sm" /> Physical ID checked against depositor name</label>
                <x-mary-textarea label="Override note (required)" wire:model="rescueNote" placeholder="Who claimed, what ID was shown…" rows="2" />
                <div class="text-xs opacity-60">Release is flagged in the audit view. Depositor is notified.</div>
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.rescueModal = false" />
                <x-mary-button wire:click="submitRescue" label="Release (override)" class="btn-error" />
            </div>
        @endif
    </x-mary-modal>

    <x-mary-modal wire:model="detailModal" title="Deposit detail">
        @php $d = $this->depositById($detailDepositId); @endphp
        @if ($d)
            <div class="p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="opacity-60">Slot</span><b class="font-mono">{{ str_pad($d['slot'], 2, '0', STR_PAD_LEFT) }}</b></div>
                <div class="flex justify-between"><span class="opacity-60">Depositor</span><b>{{ $d['depositor'] }}</b></div>
                <div class="flex justify-between"><span class="opacity-60">Items</span><span>{{ $d['item_count'] }} — {{ $d['description'] }}</span></div>
                <div class="flex justify-between"><span class="opacity-60">Since</span><span>{{ $d['deposited_at'] }}</span></div>
                <div class="flex justify-between"><span class="opacity-60">Status</span><span>{{ $d['unclaimed'] ? 'unclaimed' : $d['status'] }}</span></div>
                <div class="flex justify-between"><span class="opacity-60">Photo</span><span>{{ $d['photo'] ? 'attached' : 'none' }}</span></div>
                @if (! empty($d['events']))
                    <div class="pt-2 text-xs opacity-70">
                        @foreach ($d['events'] as $e) <div>{{ $e }}</div> @endforeach
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 px-4 pb-4">
                <x-mary-button wire:click="tempAccess({{ $d['id'] }})" label="Temp access" class="btn-ghost btn-sm" />
                <x-mary-button wire:click="openRescue({{ $d['id'] }})" label="Rescue" class="btn-ghost btn-sm" />
                <x-mary-button wire:click="claim({{ $d['id'] }})" label="Claim" class="btn-primary btn-sm" />
            </div>
        @endif
    </x-mary-modal>

    {{-- ============================ SWITCHER (local only) ============================ --}}
    @env('local')
        @php
            $order = ['A' => 'Scan command deck', 'B' => 'Slot board', 'C' => 'Ledger'];
            $keys = array_keys($order);
            $idx = array_search($variant, $keys);
            $prev = $keys[($idx - 1 + count($keys)) % count($keys)];
            $next = $keys[($idx + 1) % count($keys)];
        @endphp
        <div class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-full bg-neutral px-4 py-2 text-neutral-content shadow-xl">
            <a href="?variant={{ $prev }}" wire:navigate class="text-lg leading-none hover:opacity-70">←</a>
            <span class="text-sm font-mono">{{ $variant }} · {{ $order[$variant] ?? '?' }}</span>
            <a href="?variant={{ $next }}" wire:navigate class="text-lg leading-none hover:opacity-70">→</a>
        </div>
        <script>
            document.addEventListener('keydown', (e) => {
                if (e.target.matches('input, textarea, select, [contenteditable]')) return;
                if (e.key === 'ArrowLeft') window.location.href = '?variant={{ $prev }}';
                if (e.key === 'ArrowRight') window.location.href = '?variant={{ $next }}';
            });
        </script>
    @endenv
</div>
