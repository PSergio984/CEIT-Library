{{-- Import/Export dropdown + two-phase import modal (upload → dry-run preview → queued commit) --}}
<div>
    <x-mary-dropdown label="Import / Export" icon="o-chevron-down" class="btn-outline" no-x-anchor right>
        <x-mary-menu-item title="Import from Excel" icon="o-arrow-up-tray" wire:click="openImport"
            x-on:click="open = false" />
        <x-mary-menu-item title="Download blank template" icon="o-document-arrow-down"
            wire:click="downloadTemplate" x-on:click="open = false" />
        <x-mary-menu-item title="Export catalog" icon="o-arrow-down-tray" wire:click="exportCatalog"
            x-on:click="open = false" />
    </x-mary-dropdown>

    <x-mary-modal wire:model="showImportModal" title="Import Catalog from Excel" class="backdrop-blur-sm"
        box-class="max-w-4xl">
        <div class="space-y-4">
            <x-mary-file wire:model="importFile" label="Staff catalog workbook" hint=".xlsx — one sheet per department"
                accept=".xlsx,.xls" />

            <div wire:loading wire:target="importFile" class="flex items-center gap-2 text-sm text-base-content/70">
                <span class="loading loading-spinner loading-sm"></span>
                Parsing and validating — nothing is written yet…
            </div>

            @if ($preview)
                @php
                    $sheets = collect($preview['sheets']);
                    $messages = $sheets->flatMap(
                        fn($s, $name) => collect($s['messages'])->map(fn($m) => $m + ['sheet' => $name])
                    );
                    $totalImported = $sheets->sum('imported');
                    $totalErrors = $sheets->sum('errors');
                    $totalDuplicates = $sheets->sum('duplicates');
                @endphp

                {{-- Per-sheet counts --}}
                <div class="overflow-x-auto rounded-lg border border-base-300">
                    <table class="table table-sm">
                        <thead class="bg-base-200">
                            <tr>
                                <th>Sheet</th>
                                <th class="text-right">Valid</th>
                                <th class="text-right">Errors</th>
                                <th class="text-right">Duplicates</th>
                                <th class="text-right">New codes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sheets as $name => $sheet)
                                <tr>
                                    <td class="font-medium">{{ $name }}</td>
                                    @if ($sheet['error'])
                                        <td colspan="4" class="text-error text-xs">{{ $sheet['error'] }}</td>
                                    @else
                                        <td class="text-right">{{ $sheet['imported'] }}</td>
                                        <td class="text-right {{ $sheet['errors'] ? 'text-error font-semibold' : '' }}">
                                            {{ $sheet['errors'] }}</td>
                                        <td class="text-right">{{ $sheet['duplicates'] }}</td>
                                        <td class="text-right">{{ $sheet['generated_codes'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Row issues: sheet, row number, reason — no row data beyond the identifier --}}
                @if ($messages->isNotEmpty())
                    <div>
                        <h4 class="text-sm font-semibold mb-2">Row issues</h4>
                        <div class="overflow-x-auto rounded-lg border border-base-300 max-h-64 overflow-y-auto">
                            <table class="table table-xs">
                                <thead class="bg-base-200 sticky top-0">
                                    <tr>
                                        <th>Sheet</th>
                                        <th class="text-right">Row</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($messages->take(100) as $message)
                                        <tr>
                                            <td class="whitespace-nowrap">{{ $message['sheet'] }}</td>
                                            <td class="text-right">{{ $message['row'] ?? '—' }}</td>
                                            <td>
                                                <span
                                                    class="badge badge-xs {{ $message['type'] === 'error' ? 'badge-error' : 'badge-warning' }}">
                                                    {{ $message['type'] }}
                                                </span>
                                            </td>
                                            <td class="text-xs">{{ $message['message'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($sheets->sum('message_count') > 100)
                            <p class="text-xs text-base-content/60 mt-1">Showing the first 100 of
                                {{ $sheets->sum('message_count') }} issues.</p>
                        @endif
                    </div>
                @endif

                <p class="text-sm text-base-content/70">
                    {{ $totalImported }} paper{{ $totalImported === 1 ? '' : 's' }} ready to import
                    @if ($totalDuplicates)
                        · {{ $totalDuplicates }} duplicate{{ $totalDuplicates === 1 ? '' : 's' }} will be skipped
                    @endif
                    @if ($totalErrors)
                        · <span class="text-error">{{ $totalErrors }} row{{ $totalErrors === 1 ? '' : 's' }}
                            with errors</span>
                    @endif
                </p>
            @endif
        </div>

        <x-slot:actions>
            <button wire:click="downloadFailureReport" class="btn btn-ghost btn-sm"
                @disabled(! $preview || $sheets->sum('errors') === 0)
                wire:loading.attr="disabled" wire:target="downloadFailureReport">
                Download failure report
            </button>
            <div class="flex-1"></div>
            <button wire:click="closeImportModal" class="btn btn-ghost">Cancel</button>
            <button wire:click="confirmImport" class="btn btn-primary gap-2" @disabled(! $preview)
                wire:loading.attr="disabled" wire:target="importFile,confirmImport">
                <span wire:loading.remove wire:target="importFile,confirmImport">Import</span>
                <span wire:loading wire:target="confirmImport" class="loading loading-spinner loading-xs"></span>
                <span wire:loading wire:target="confirmImport">Queuing…</span>
            </button>
        </x-slot:actions>
    </x-mary-modal>
</div>
