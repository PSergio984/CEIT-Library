<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Livewire\Attributes\Title;

#[Title('Borrow Logs')]
class AdminBorrowTransactions extends AdminComponent
{
    use WithPagination, Toast;

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
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-16'],
        ['key' => 'email', 'label' => 'Email', 'class' => 'min-w-32'],
        ['key' => 'title', 'label' => 'Title Borrowed', 'sortable' => true, 'class' => 'min-w-48'],
        ['key' => 'paper_type', 'label' => 'Type', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'notes', 'label' => 'Notes', 'class' => 'min-w-40'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-24', 'sortable' => false],
    ];

    // Sort configuration for MaryUI
    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    protected function getTransactionsQuery()
    {
        return BorrowTransaction::with([
            'user',
            'inventory.academicPaper'
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
                    'user_name' => trim(($transaction->user?->first_name ?? '') . ' ' . ($transaction->user?->last_name ?? '')) ?: 'N/A',
                    'email' => $transaction->user?->email ?? 'N/A',
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

        if (!$transaction) {
            $this->error("Transaction not found!");
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

        if (!$transaction) {
            $this->error("Transaction not found!");
            return;
        }

        if ($this->editStatus === 'completed' && empty($this->editTimeOut)) {
            $this->error("Time Out is required when status is completed!");
            return;
        }

        $transaction->update([
            'status' => $this->editStatus,
            'time_out' => $this->editStatus === 'completed'
                ? \Carbon\Carbon::parse($this->editTimeOut)
                : null,
        ]);

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

        if (!$data || !isset($data['p'])) {
            \Log::error('Invalid QR format - missing p key');
            $this->error("Invalid QR code format!");
            return ['found' => false];
        }

        // Extract the nested data
        $borrowData = $data['p'];
        \Log::info('Borrow Data:', ['borrowData' => $borrowData]);

        // Validate required fields
        if (!isset($borrowData['inventory_id']) || !isset($borrowData['paper_id'])) {
            \Log::error('Missing required fields', [
                'has_inventory_id' => isset($borrowData['inventory_id']),
                'has_paper_id' => isset($borrowData['paper_id'])
            ]);
            $this->error("Missing required data in QR code!");
            return ['found' => false];
        }

        // Find the inventory and paper
        $inventory = Inventory::with('academicPaper')->find($borrowData['inventory_id']);
        $paper = AcademicPaper::find($borrowData['paper_id']);
        $userEmail = $borrowData['lat'] ?? null;
        \Log::info('Looking for user with email:', ['email' => $userEmail]);
        $user = User::where('email', $userEmail)->first();

        \Log::info('Database lookups:', [
            'inventory_found' => !!$inventory,
            'paper_found' => !!$paper,
            'user_found' => !!$user,
            'user_email' => $userEmail
        ]);

        if (!$inventory || !$paper) {
            \Log::error('Invalid inventory or paper', [
                'inventory_id' => $borrowData['inventory_id'],
                'paper_id' => $borrowData['paper_id']
            ]);
            $this->error("Invalid inventory or paper ID!");
            return ['found' => false];
        }

        if (!$user) {
            \Log::error('User not found', ['email' => $userEmail]);
            $this->error("User not found with email: {$userEmail}");
            return ['found' => false];
        }

        // Check if inventory is available
        \Log::info('Inventory status:', ['status' => $inventory->status]);
        if ($inventory->status !== 'Available') {
            \Log::warning('Inventory not available', [
                'inventory_id' => $inventory->id,
                'status' => $inventory->status
            ]);
            $this->error("This copy is not available for borrowing! Current status: {$inventory->status}");
            return ['found' => false];
        }

        // Store the pending borrow data
        $this->pendingBorrowData = [
            'user_id' => $user->id,
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'user_email' => $user->email,
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
        return ['found' => true];

    } catch (\Exception $e) {
        \Log::error('QR Processing Exception:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        $this->error("Error processing QR code: " . $e->getMessage());
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
                $this->error("No pending borrow request!");
                return;
            }

            // Start database transaction
            \DB::beginTransaction();

            // Create borrow transaction
            $transaction = BorrowTransaction::create([
                'user_id' => $this->pendingBorrowData['user_id'],
                'academic_paper_id' => $this->pendingBorrowData['paper_id'],
                'inventory_id' => $this->pendingBorrowData['inventory_id'],
                'time_in' => now(),
                'time_out' => null,
                'status' => 'started',
                'expires_at' => $this->pendingBorrowData['expires_at']
                    ? \Carbon\Carbon::createFromTimestamp($this->pendingBorrowData['expires_at'])
                    : now()->addHours(2),
                'session_token' => bin2hex(random_bytes(32)),
                'notes' => $this->borrowNotes ?: null,
            ]);

            // Update inventory status to Unavailable
            $inventory = Inventory::find($this->pendingBorrowData['inventory_id']);
            $inventory->update(['status' => 'Unavailable']);

            \DB::commit();

            $this->success("Borrow transaction created successfully! Copy #{$inventory->copy_number} is now unavailable.");
            $this->closeConfirmBorrowModal();
            $this->reset(['borrowNotes', 'pendingBorrowData']);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Borrow Confirmation Error: ' . $e->getMessage());
            $this->error("Failed to create borrow transaction: " . $e->getMessage());
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

    public function render()
    {
        return view('livewire.pages.admin.admin-borrow-transactions');
    }
}
