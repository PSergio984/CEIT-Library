<?php

namespace App\Services;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BorrowService
{
    use CreatesQrCanonicalMessage;

    /**
     * Process a scanned QR code for borrowing or returning a book
     */
    public function processScannedQr(string $qrData): array
    {
        Log::info('=== QR Processing Started (Service) ===');

        try {
            // Parse the JSON data from QR code
            $data = json_decode($qrData, true);

            // ENFORCE encryption
            if (! $data || ! isset($data['encrypted'])) {
                Log::warning('Unencrypted or invalid QR format detected');

                return ['success' => false, 'message' => 'Invalid QR code format! Only official encrypted QR codes are accepted.'];
            }

            $decryptedData = $this->decryptQrData($data['encrypted']);

            if (! $decryptedData) {
                Log::error('Failed to decrypt QR data');

                return ['success' => false, 'message' => 'Invalid or corrupted QR code!'];
            }

            $data = $decryptedData;

            // --- REPLAY ATTACK PROTECTION ---

            if (! is_array($data) || ! isset($data['hash'], $data['nonce'], $data['timestamp'])) {
                Log::warning('Borrow QR code missing required security fields (hash, nonce, or timestamp)');

                return ['success' => false, 'message' => 'Security verification failed. Missing required security fields.'];
            }

            // 1. Verify HMAC signature (unconditional)
            $secret = config('app.qr_hmac_secret');
            $dataForCanonical = $data;
            unset($dataForCanonical['hash']);
            $canonicalMessage = $this->createCanonicalMessage($dataForCanonical);
            $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);

            if (! hash_equals($expectedHash, $data['hash'])) {
                Log::warning('Borrow QR code hash mismatch - possible tampering');

                return ['success' => false, 'message' => 'Security verification failed. This QR code may have been tampered with.'];
            }

            // 2. Timestamp freshness (Removed for Offline Accessibility - Phase 7)
            // We intentionally ignore the timestamp difference to allow downloaded/screenshot QR codes to work offline.

            // 3. Nonce Replay Prevention (unconditional)
            $nonceKey = 'qr_nonce:'.$data['nonce'];
            if (! Cache::add($nonceKey, true, 150)) {
                Log::warning('Borrow QR code rejected: Replay attack detected (nonce reuse)');

                return ['success' => false, 'message' => 'This QR code has already been used.'];
            }

            if (! $data || ! isset($data['p'])) {
                Log::error('Invalid QR format - missing p key');

                return ['success' => false, 'message' => 'Invalid QR code format!'];
            }

            $borrowData = $data['p'];

            if (! isset($borrowData['inventory_id']) || ! isset($borrowData['paper_id'])) {
                return ['success' => false, 'message' => 'Missing required data in QR code!'];
            }

            $inventory = Inventory::with('academicPaper')->find($borrowData['inventory_id']);
            $paper = AcademicPaper::find($borrowData['paper_id']);
            $user = isset($borrowData['requested_by']) ? User::find($borrowData['requested_by']) : null;

            if (! $inventory || ! $paper) {
                return ['success' => false, 'message' => 'Invalid inventory or paper ID!'];
            }

            if (! $user) {
                return ['success' => false, 'message' => 'User not found!'];
            }

            if ($inventory->status === 'Unavailable') {
                return $this->handleReturn($inventory, $user);
            } elseif ($inventory->status === 'Available') {
                return $this->prepareBorrow($inventory, $paper, $user, $borrowData);
            } else {
                return ['success' => false, 'message' => "This book cannot be borrowed. Current status: {$inventory->status}"];
            }

        } catch (\Exception $e) {
            Log::error('QR Processing Exception:', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Error processing QR code: '.$e->getMessage()];
        }
    }

    /**
     * Handle the return of a book
     */
    protected function handleReturn(Inventory $inventory, User $user): array
    {
        $activeTransaction = BorrowTransaction::where('inventory_id', $inventory->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->first();

        if ($activeTransaction) {
            if ($activeTransaction->user_id !== $user->id) {
                return ['success' => false, 'message' => 'This book was borrowed by another student. Only the original borrower\'s QR code can return it.'];
            }

            DB::beginTransaction();
            try {
                $activeTransaction->update([
                    'time_out' => now(),
                    'status' => 'completed',
                ]);

                $inventory->update(['status' => 'Available']);

                DB::commit();

                return [
                    'success' => true,
                    'action' => 'returned',
                    'message' => "Book returned successfully! Copy #{$inventory->copy_number} is now available.",
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to return book: '.$e->getMessage()];
            }
        }

        return ['success' => false, 'message' => 'This book is marked as unavailable but has no active transaction.'];
    }

    /**
     * Prepare data for borrow confirmation
     */
    protected function prepareBorrow(Inventory $inventory, AcademicPaper $paper, User $user, array $borrowData): array
    {
        $existingActiveTransaction = BorrowTransaction::where('inventory_id', $inventory->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->first();

        if ($existingActiveTransaction) {
            return ['success' => false, 'message' => 'This book is currently borrowed and must be returned before it can be borrowed again.'];
        }

        return [
            'success' => true,
            'action' => 'borrow_prepared',
            'data' => [
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
            ],
        ];
    }

    /**
     * Confirm a borrow transaction
     */
    public function confirmBorrow(array $pendingData, ?string $notes = null): array
    {
        try {
            DB::beginTransaction();

            $inventory = Inventory::lockForUpdate()->find($pendingData['inventory_id']);

            if (! $inventory || $inventory->status !== 'Available') {
                DB::rollBack();

                return ['success' => false, 'message' => 'This copy is no longer available!'];
            }

            $inventory->update(['status' => 'Unavailable']);

            $timeIn = now();
            $transaction = BorrowTransaction::create([
                'user_id' => $pendingData['user_id'],
                'academic_paper_id' => $pendingData['paper_id'],
                'inventory_id' => $pendingData['inventory_id'],
                'time_in' => $timeIn,
                'time_out' => null,
                'status' => 'started',
                'expires_at' => $timeIn->copy()->addHours(3),
                'session_token' => bin2hex(random_bytes(32)),
                'notes' => $notes ?: null,
            ]);

            $paper = AcademicPaper::find($pendingData['paper_id']);

            DB::commit();

            try {
                app(NotificationService::class)->notify(
                    User::find($pendingData['user_id']),
                    'paper_borrowed',
                    'Academic Paper Borrowed Successfully',
                    "You have successfully borrowed \"{$paper->title}\". Please return it by ".$transaction->expires_at->format('M d, Y h:i A').'.',
                    [
                        'transaction_id' => $transaction->id,
                        'paper_id' => $paper->id,
                        'paper_title' => $paper->title,
                        'inventory_id' => $inventory->id,
                        'copy_number' => $inventory->copy_number,
                        'expires_at' => $transaction->expires_at->toIso8601String(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send borrow notification: '.$e->getMessage());
            }

            return [
                'success' => true,
                'message' => "Borrow transaction created successfully! Copy #{$inventory->copy_number} is now unavailable.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Borrow Confirmation Error: '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to create borrow transaction: '.$e->getMessage()];
        }
    }
}
