<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Borrow Logs')]
#[Lazy]
class AdminBorrowTransactions extends AdminComponent
{
    use CreatesQrCanonicalMessage, Toast, WithPagination;

    public $perPage = 20;

    public $search = '';

    public $paperTypeFilter = '';

    public $statusFilter = '';

    public $selectedDate = '';

    // Edit modal properties
    public $showEditModal = false;

    public $editingTransactionId = null;

    public $editStatus = '';

    public $editTimeOut = '';

    // QR Scanner modal properties
    public $showQrModal = false;

    public $scannedQrData = '';

    public $isProcessingQr = false;

    // Borrow confirmation modal properties
    public $showConfirmBorrowModal = false;

    public $pendingBorrowData = [];

    public $borrowNotes = '';

    // MaryUI table headers - Optimized for responsive display
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-32'],

        ['key' => 'title', 'label' => 'Title Borrowed', 'sortable' => true, 'class' => 'min-w-40'],
        ['key' => 'paper_type', 'label' => 'Type', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'notes', 'label' => 'Notes', 'class' => 'w-28'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20 text-center', 'sortable' => false],
    ];

    // Sort configuration for MaryUI
    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    // Check if user can edit transactions (admin only)
    public function getCanEditProperty(): bool
    {
        return Gate::allows('manage-borrow-logs');
    }

    protected function getTransactionsQuery()
    {
        return BorrowTransaction::with([
            'user',
            'inventory.academicPaper',
        ])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('user', function ($q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%");
                    })
                        ->orWhereHas('inventory.academicPaper', function ($q) {
                            $q->where('title', 'like', "%{$this->search}%");
                        })
                        ->orWhere('notes', 'like', "%{$this->search}%");
                });
            })
            ->when($this->paperTypeFilter, function ($query) {
                $query->whereHas('inventory.academicPaper', function ($q) {
                    $q->where('paper_type', $this->paperTypeFilter);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->selectedDate, function ($query) {
                $query->whereDate('time_in', $this->selectedDate);
            });
    }

    public function getTransactionsProperty()
    {
        $query = $this->getTransactionsQuery();

        // Apply sorting based on MaryUI sortBy
        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $column = $this->sortBy['column'];
            $direction = $this->sortBy['direction'];

            switch ($column) {
                case 'user_name':
                    $query->join('users', 'borrow_transactions.user_id', '=', 'users.id')
                        ->orderBy('users.first_name', $direction)
                        ->select('borrow_transactions.*');
                    break;
                case 'title':
                    $query->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
                        ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
                        ->orderBy('academic_papers.title', $direction)
                        ->select('borrow_transactions.*');
                    break;
                case 'paper_type':
                    $query->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
                        ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
                        ->orderBy('academic_papers.paper_type', $direction)
                        ->select('borrow_transactions.*');
                    break;
                default:
                    $query->orderBy($column, $direction);
            }
        } else {
            $query->orderBy('time_in', 'desc');
        }

        return $query->paginate($this->perPage)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user_name' => trim(($transaction->user?->first_name ?? '').' '.($transaction->user?->last_name ?? '')) ?: 'N/A',
                    'user' => $transaction->user,
                    'title' => $transaction->inventory?->academicPaper?->title ?? 'No Title',
                    'paper_type' => $transaction->inventory?->academicPaper?->paper_type ?? 'N/A',
                    'time_in' => $transaction->time_in,
                    'time_out' => $transaction->time_out,
                    'status' => $transaction->status ?? 'active',
                    'notes' => $transaction->notes ?? 'N/A',
                    'original' => $transaction,
                ];
            });
    }

    public function getPaperTypesProperty()
    {
        return AcademicPaper::distinct()->pluck('paper_type')->filter();
    }

    // Edit modal methods
    public function openEditModal($transactionId)
    {
        $transaction = BorrowTransaction::find($transactionId);

        if (! $transaction) {
            $this->error('Transaction not found!');

            return;
        }

        $this->editingTransactionId = $transactionId;
        $this->editStatus = $transaction->status ?? 'started';
        $this->editTimeOut = $transaction->time_out ? $transaction->time_out->format('Y-m-d\TH:i') : '';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingTransactionId = null;
        $this->editStatus = '';
        $this->editTimeOut = '';
    }

    public function saveTransaction()
    {
        $this->validate([
            'editStatus' => 'required|in:started,completed',
            'editTimeOut' => 'nullable|date',
        ]);

        $transaction = BorrowTransaction::find($this->editingTransactionId);

        if (! $transaction) {
            $this->error('Transaction not found!');

            return;
        }

        if ($this->editStatus === 'completed' && empty($this->editTimeOut)) {
            $this->error('Time Out is required when status is completed!');

            return;
        }

        \DB::transaction(function () use ($transaction) {
            $transaction->update([
                'status' => $this->editStatus,
                'time_out' => $this->editStatus === 'completed'
                    ? \Carbon\Carbon::parse($this->editTimeOut)
                    : null,
            ]);

            $inventory = $transaction->inventory()->lockForUpdate()->first();

            if ($inventory) {
                $inventory->update([
                    'status' => $this->editStatus === 'completed' ? 'Available' : 'Unavailable',
                ]);
            }
        });

        $this->success("Transaction #$this->editingTransactionId updated successfully!");
        $this->closeEditModal();
    }

    // QR Scanner methods
    public function openQrModal()
    {
        $this->showQrModal = true;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-opened');
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-closed');
    }

    public function processScannedQr($qrData)
    {
        \Log::info('=== QR Processing Started ===');
        \Log::info('Raw QR Data:', ['data' => $qrData]);

        $this->isProcessingQr = true;

        try {
            $this->scannedQrData = $qrData;

            // Parse the JSON data from QR code
            $data = json_decode($qrData, true);
            \Log::info('Decoded JSON:', ['data' => $data]);

            // Check if data is encrypted (new format)
            if (isset($data['encrypted'])) {
                \Log::info('Encrypted QR detected, decrypting...');
                $decryptedData = $this->decryptQrData($data['encrypted']);

                if (! $decryptedData) {
                    \Log::error('Failed to decrypt QR data');
                    $this->error('Invalid or corrupted QR code!');
                    $this->isProcessingQr = false;

                    return ['found' => false];
                }

                $data = $decryptedData;
                \Log::info('Decrypted Data:', ['data' => $data]);
            }

            if (! $data || ! isset($data['p'])) {
                \Log::error('Invalid QR format - missing p key');
                $this->error('Invalid QR code format!');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            // Extract the nested data
            $borrowData = $data['p'];
            \Log::info('Borrow Data:', ['borrowData' => $borrowData]);

            // Validate required fields
            if (! isset($borrowData['inventory_id']) || ! isset($borrowData['paper_id'])) {
                \Log::error('Missing required fields', [
                    'has_inventory_id' => isset($borrowData['inventory_id']),
                    'has_paper_id' => isset($borrowData['paper_id']),
                ]);
                $this->error('Missing required data in QR code!');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            // Find the inventory and paper
            $inventory = Inventory::with('academicPaper')->find($borrowData['inventory_id']);
            $paper = AcademicPaper::find($borrowData['paper_id']);

            // Try to find user by ID (requested_by) only
            $user = null;
            if (isset($borrowData['requested_by'])) {
                $userId = $borrowData['requested_by'];
                \Log::info('Looking for user with ID:', ['id' => $userId]);
                $user = User::find($userId);
            }

            \Log::info('Database lookups:', [
                'inventory_found' => (bool) $inventory,
                'paper_found' => (bool) $paper,
                'user_found' => (bool) $user,
                'user_data' => $user ? ['id' => $user->id] : null,
            ]);

            if (! $inventory || ! $paper) {
                \Log::error('Invalid inventory or paper', [
                    'inventory_id' => $borrowData['inventory_id'],
                    'paper_id' => $borrowData['paper_id'],
                ]);
                $this->error('Invalid inventory or paper ID!');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            if (! $user) {
                \Log::error('User not found', [
                    'requested_by' => $borrowData['requested_by'] ?? 'not set',
                ]);
                $this->error('User not found!');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            // Check inventory status
            \Log::info('Inventory status:', ['status' => $inventory->status]);

            if ($inventory->status === 'Unavailable') {
                // Book is currently borrowed - handle return
                \Log::info('Book is unavailable - checking for active transaction');

                $activeTransaction = BorrowTransaction::where('inventory_id', $inventory->id)
                    ->whereIn('status', ['started', 'overdue']) // Include overdue transactions
                    ->whereNull('time_out')
                    ->first();

                if ($activeTransaction) {
                    // Return the book
                    \DB::beginTransaction();
                    try {
                        // Update status to overdue if it wasn't already and the book is late
                        if ($activeTransaction->status === 'started' && $activeTransaction->isOverdue()) {
                            $activeTransaction->status = 'overdue';
                        }

                        // Complete the transaction
                        $activeTransaction->update([
                            'time_out' => now(),
                            'status' => 'completed',
                        ]);

                        $inventory->update(['status' => 'Available']);

                        \DB::commit();

                        $this->isProcessingQr = false; // Reset processing flag so camera can scan again
                        $this->success("Book returned successfully! Copy #{$inventory->copy_number} is now available.");
                        \Log::info('Book returned successfully');

                        return ['found' => true, 'action' => 'returned'];
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        \Log::error('Return error:', ['error' => $e->getMessage()]);
                        $this->error('Failed to return book: '.$e->getMessage());
                        $this->isProcessingQr = false;

                        return ['found' => false];
                    }
                } else {
                    \Log::warning('Book marked unavailable but no active transaction found');
                    $this->error('This book is marked as unavailable but has no active transaction. Please check manually.');
                    $this->isProcessingQr = false;

                    return ['found' => false];
                }
            } elseif ($inventory->status === 'Available') {
                // Book is available - prepare to borrow
                \Log::info('Book is available - preparing borrow confirmation');

                $this->pendingBorrowData = [
                    'user_id' => $user->id,
                    'user_name' => $user->first_name.' '.$user->last_name,
                    'inventory_id' => $inventory->id,
                    'paper_id' => $paper->id,
                    'copy_number' => $inventory->copy_number,
                    'catalog_code' => $paper->catalog_code,
                    'title' => $paper->title,
                    'paper_type' => $paper->paper_type,
                    'publication_year' => $paper->publication_year,
                    'department' => $paper->department,
                    'requested_by' => $borrowData['requested_by'] ?? null,
                    'expires_at' => $borrowData['exp'] ?? null,
                ];

                \Log::info('Pending borrow data prepared:', $this->pendingBorrowData);

                // Close QR modal and open confirmation modal
                $this->closeQrModal();
                $this->showConfirmBorrowModal = true;
                $this->borrowNotes = '';

                \Log::info('=== QR Processing Successful ===');

                return ['found' => true, 'action' => 'borrow_prepared'];
            } else {
                // Book has other status (Lost, Damaged, etc.)
                \Log::warning('Book has non-borrowable status', [
                    'inventory_id' => $inventory->id,
                    'status' => $inventory->status,
                ]);
                $this->error("This book cannot be borrowed. Current status: {$inventory->status}");
                $this->isProcessingQr = false;

                return ['found' => false];
            }
        } catch (\Exception $e) {
            \Log::error('QR Processing Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Error processing QR code: '.$e->getMessage());
            $this->isProcessingQr = false;

            return ['found' => false];
        } finally {
            $this->isProcessingQr = false;
            \Log::info('=== QR Processing Ended ===');
        }
    }

    public function closeConfirmBorrowModal()
    {
        $this->showConfirmBorrowModal = false;
        $this->pendingBorrowData = [];
        $this->borrowNotes = '';
    }

    public function confirmBorrow()
    {
        try {
            if (empty($this->pendingBorrowData)) {
                $this->error('No pending borrow request!');

                return;
            }

            // Start database transaction
            \DB::beginTransaction();

            // Create borrow transaction with 3-hour expiry from time_in
            $timeIn = now();
            $transaction = BorrowTransaction::create([
                'user_id' => $this->pendingBorrowData['user_id'],
                'academic_paper_id' => $this->pendingBorrowData['paper_id'],
                'inventory_id' => $this->pendingBorrowData['inventory_id'],
                'time_in' => $timeIn,
                'time_out' => null,
                'status' => 'started',
                'expires_at' => $timeIn->copy()->addHours(3), // 3 hours from borrow time
                'session_token' => bin2hex(random_bytes(32)),
                'notes' => $this->borrowNotes ?: null,
            ]);

            // Update inventory status to Unavailable
            $inventory = Inventory::lockForUpdate()->find($this->pendingBorrowData['inventory_id']);

            if (! $inventory || $inventory->status !== 'Available') {
                \DB::rollBack();
                $this->error('This copy is no longer available!');
                $this->closeConfirmBorrowModal();

                return;
            }

            $inventory->update(['status' => 'Unavailable']);

            // Create notification for the borrower
            $paper = \App\Models\AcademicPaper::find($this->pendingBorrowData['paper_id']);
            $expiresAt = $transaction->expires_at;

            \App\Models\Notification::create([
                'user_id' => $this->pendingBorrowData['user_id'],
                'type' => 'paper_borrowed',
                'title' => 'Academic Paper Borrowed Successfully',
                'message' => "You have successfully borrowed \"{$paper->title}\". Please return it by ".$expiresAt->format('M d, Y h:i A').'.',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'paper_id' => $paper->id,
                    'paper_title' => $paper->title,
                    'inventory_id' => $inventory->id,
                    'copy_number' => $inventory->copy_number,
                    'expires_at' => $this->pendingBorrowData['expires_at'],
                ],
            ]);

            \DB::commit();

            $this->success("Borrow transaction created successfully! Copy #{$inventory->copy_number} is now unavailable.");
            $this->closeConfirmBorrowModal();
            $this->reset(['borrowNotes', 'pendingBorrowData']);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Borrow Confirmation Error: '.$e->getMessage());
            $this->error('Failed to create borrow transaction: '.$e->getMessage());
        }
    }

    // Filter methods
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedDate()
    {
        $this->resetPage();
    }

    public function updatingPaperTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->paperTypeFilter = '';
        $this->selectedDate = '';
        $this->statusFilter = '';
        $this->sortBy = ['column' => 'time_in', 'direction' => 'desc'];
        $this->resetPage();
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading borrow transactions...',
            'subtext' => 'Please wait while we fetch the transaction data',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-borrow-transactions');
    }
}
