<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Borrow Transactions</h1>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="label">
                    <span class="label-text">Search</span>
                </label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, title..."
                    class="input input-bordered w-full" />
            </div>

            <div>
                <label class="label">
                    <span class="label-text">Paper Type</span>
                </label>
                <select wire:model.live="paperTypeFilter" class="select select-bordered w-full">
                    <option value="">All Types</option>
                    @foreach ($paperTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">
                    <span class="label-text">Year From</span>
                </label>
                <input type="number" wire:model.live="yearFrom" placeholder="2020" min="1900"
                    max="{{ date('Y') }}" class="input input-bordered w-full" />
            </div>

            <div>
                <label class="label">
                    <span class="label-text">Year To</span>
                </label>
                <input type="number" wire:model.live="yearTo" placeholder="2024" min="1900"
                    max="{{ date('Y') }}" class="input input-bordered w-full" />
            </div>

            <div class="flex items-end">
                <button wire:click="clearFilters" class="btn btn-outline w-full">
                    Clear Filters
                </button>
            </div>
        </div>
    </div>

    <div class="mb-4 text-sm text-base-content/70">
        Showing {{ $transactions->count() }} of {{ $transactions->total() }} results
    </div>

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full text-sm">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Student No.</th>
                    <th>Title Borrowed</th>
                    <th>Authors</th>
                    <th>Paper Type</th>
                    <th>Publication Year</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ trim(($transaction->user?->first_name ?? '') . ' ' . ($transaction->user?->last_name ?? '')) ?: 'N/A' }}
                        </td>
                        <td>{{ $transaction->user?->email ?? 'N/A' }}</td>
                        <td>{{ $transaction->user?->student_no ?? 'N/A' }}</td>

                        <td>{{ $transaction->inventory?->academicPaper?->title ?? 'No Title' }}</td>
                        <td>{{ collect(data_get($transaction, 'inventory.academicPaper.authors', []))->pluck('name')->join(', ') ?: 'No Authors' }}
                        </td>
                        <td>{{ $transaction->inventory?->academicPaper?->paper_type ?? 'N/A' }}</td>
                        <td>{{ $transaction->inventory?->academicPaper?->publication_year ?? 'N/A' }}</td>

                        <td>{{ $transaction->time_in?->format('M d, Y H:i') ?? 'N/A' }}</td>
                        <td>{{ $transaction->time_out?->format('M d, Y H:i') ?? 'Active' }}</td>
                        <td>
                            <span
                                class="badge badge-{{ $transaction->status == 'completed' ? 'success' : 'warning' }}">
                                {{ ucfirst($transaction->status ?? 'active') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-8">
                            No transactions found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $transactions->links() }}
    </div>
</div>
