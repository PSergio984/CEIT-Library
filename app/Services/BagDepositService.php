<?php

namespace App\Services;

use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\BagSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BagDepositService
{
    public const INVALID = 'invalid';

    private const CLAIM_CODE_MAX_ATTEMPTS = 5;

    /**
     * Decrypt and validate a scanned QR payload, resolving it to the
     * registered user it belongs to. Mirrors the validation half of
     * AttendanceService: timestamp skew is not enforced (offline QRs) and
     * the nonce is consumed only after user lookup succeeds, so failed
     * lookups never burn a nonce.
     */
    public function resolveScannedUser(string $qrData): User|string
    {
        $data = app(QrPayloadValidator::class)->validate($qrData, enforceTimestampSkew: false, consumeNonce: false);

        if ($data === QrPayloadValidator::INVALID) {
            return self::INVALID;
        }

        $user = User::find($data['user_id']);
        if (! $user) {
            Log::warning('User not found during bag counter QR scan', ['user_id' => $data['user_id']]);

            return self::INVALID;
        }

        // Check rate limiting per user
        $rateLimitKey = 'qr_rate_limit:'.$data['user_id'];
        $recentScans = Cache::get($rateLimitKey, 0);

        if ($recentScans >= 60) {
            Log::warning('Rate limit exceeded for user', ['user_id' => $data['user_id']]);

            return self::INVALID;
        }

        // Nonce Replay Prevention (One-time use check)
        $nonceKey = 'qr_nonce:'.$data['nonce'];
        if (! Cache::add($nonceKey, true, 150)) {
            Log::warning('QR code rejected: Replay attack detected (nonce reuse)', [
                'user_id' => $data['user_id'],
                'nonce' => $data['nonce'],
            ]);

            return self::INVALID;
        }

        Cache::put($rateLimitKey, $recentScans + 1, 60);

        return $user;
    }

    /**
     * Open a deposit: bind the depositor to an available slot and issue a
     * six-digit claim code unique among open deposits.
     *
     * @throws RuntimeException when the slot cannot receive deposits
     */
    public function openDeposit(User $depositor, BagSlot $slot, int $itemCount, ?string $description, User $scanner): BagDeposit
    {
        [$scannedBy, $scannedByAdminId] = $this->scannerIdentity($scanner);

        for ($attempt = 0; $attempt < self::CLAIM_CODE_MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($depositor, $slot, $itemCount, $description, $scannedBy, $scannedByAdminId) {
                    // Re-check inside the transaction: the slot may have been
                    // filled or taken out of service since it was picked.
                    $slot = BagSlot::lockForUpdate()->find($slot->id);

                    if (! $slot || ! $slot->isAvailable()) {
                        throw new RuntimeException('That slot can no longer receive deposits.');
                    }

                    $deposit = BagDeposit::create([
                        'user_id' => $depositor->id,
                        'bag_slot_id' => $slot->id,
                        'item_count' => $itemCount,
                        'description' => $description ?: null,
                        'claim_code' => $this->generateClaimCode(),
                        'status' => BagDeposit::STATUS_OPEN,
                    ]);

                    $this->recordEvent(
                        $deposit,
                        BagDepositEvent::TYPE_DEPOSIT,
                        $depositor->id,
                        $scannedBy,
                        $scannedByAdminId,
                        BagDeposit::METHOD_OWNER_SCAN,
                    );

                    return $deposit;
                });
            } catch (QueryException $e) {
                if (! $this->isClaimCodeCollision($e) || $attempt === self::CLAIM_CODE_MAX_ATTEMPTS - 1) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Could not issue a claim code.');
    }

    /**
     * Close a deposit: mark it claimed, record who collected by which
     * method, and free the slot (occupancy is derived from open deposits).
     *
     * @throws RuntimeException when the deposit is not open
     */
    public function claimDeposit(BagDeposit $deposit, User $claimant, string $method, User $scanner): BagDeposit
    {
        [$scannedBy, $scannedByAdminId] = $this->scannerIdentity($scanner);

        return DB::transaction(function () use ($deposit, $claimant, $method, $scannedBy, $scannedByAdminId) {
            $deposit = BagDeposit::lockForUpdate()->find($deposit->id);

            if (! $deposit || ! $deposit->isOpen()) {
                throw new RuntimeException('That deposit is no longer open.');
            }

            $deposit->fill([
                'status' => BagDeposit::STATUS_CLAIMED,
                'claimed_by' => $claimant->id,
                'claimed_at' => now(),
                'claim_method' => $method,
            ])->save();

            $this->recordEvent(
                $deposit,
                BagDepositEvent::TYPE_CLAIM,
                $claimant->id,
                $scannedBy,
                $scannedByAdminId,
                $method,
            );

            return $deposit;
        });
    }

    /**
     * Identify the counter operator the same way attendance does: the
     * active librarian-duty record when there is one, else the admin user.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function scannerIdentity(User $scanner): array
    {
        $scannedBy = $scanner->getActiveLibrarianDuty()?->id;

        $scannedByAdminId = null;
        if (! $scannedBy && $scanner->hasAdminAccess()) {
            $scannedByAdminId = $scanner->id;
        }

        return [$scannedBy, $scannedByAdminId];
    }

    private function recordEvent(
        BagDeposit $deposit,
        string $type,
        ?int $actorUserId,
        ?int $scannedBy,
        ?int $scannedByAdminId,
        ?string $method = null,
        ?string $note = null,
        bool $idVerified = false,
    ): BagDepositEvent {
        return BagDepositEvent::create([
            'bag_deposit_id' => $deposit->id,
            'type' => $type,
            'actor_user_id' => $actorUserId,
            'scanned_by' => $scannedBy,
            'scanned_by_admin_id' => $scannedByAdminId,
            'method' => $method,
            'note' => $note,
            'id_verified' => $idVerified,
        ]);
    }

    /**
     * Six-digit numeric code, unique among open deposits. The pre-check
     * avoids almost every collision; the partial unique index plus the
     * retry in openDeposit() closes the remaining race.
     */
    private function generateClaimCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (BagDeposit::open()->where('claim_code', $code)->exists());

        return $code;
    }

    private function isClaimCodeCollision(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'bag_deposits_open_claim_code_unique')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed: bag_deposits.claim_code');
    }
}
