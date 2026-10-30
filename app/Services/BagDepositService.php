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
use Illuminate\Support\Str;
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
    public function openDeposit(User $depositor, BagSlot $slot, int $itemCount, ?string $description, User $scanner, ?string $photoPath = null): BagDeposit
    {
        [$scannedBy, $scannedByAdminId] = $this->scannerIdentity($scanner);

        for ($attempt = 0; $attempt < self::CLAIM_CODE_MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($depositor, $slot, $itemCount, $description, $photoPath, $scannedBy, $scannedByAdminId) {
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
                        'photo_path' => $photoPath,
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
     * Look up an open deposit by its six-digit claim code. Used by the
     * third-party claim path; a miss is a wrong code, not an error.
     */
    public function findOpenDepositByCode(string $claimCode): ?BagDeposit
    {
        return BagDeposit::open()->where('claim_code', $claimCode)->first();
    }

    /**
     * Close a deposit: mark it claimed, record who collected by which
     * method, and free the slot (occupancy is derived from open deposits).
     * The depositor is notified whenever the claim cannot be proven theirs —
     * a different claimant, or a staff rescue release.
     *
     * @throws RuntimeException when the deposit is not open
     */
    public function claimDeposit(BagDeposit $deposit, User $claimant, string $method, User $scanner, ?string $note = null, bool $idVerified = false): BagDeposit
    {
        [$scannedBy, $scannedByAdminId] = $this->scannerIdentity($scanner);

        $deposit = DB::transaction(function () use ($deposit, $claimant, $method, $note, $idVerified, $scannedBy, $scannedByAdminId) {
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
                $note,
                $idVerified,
            );

            return $deposit;
        });

        if ($claimant->id !== $deposit->user_id || $method === BagDeposit::METHOD_RESCUE_LOOKUP) {
            $this->notifyDepositor(
                $deposit,
                'bag_claim',
                'Bag deposit claimed',
                "Your bag deposit in slot {$deposit->slot->number} was released via {$this->claimMethodLabel($method)}.",
                ['bag_deposit_id' => $deposit->id, 'claim_method' => $method],
            );
        }

        return $deposit;
    }

    /**
     * Record a temporary access: the slot is opened and re-locked without
     * closing the deposit. The depositor is notified when the accessor is
     * someone else.
     */
    public function tempAccess(BagDeposit $deposit, User $accessor, User $scanner): BagDepositEvent
    {
        if (! $deposit->isOpen()) {
            throw new RuntimeException('That deposit is no longer open.');
        }

        [$scannedBy, $scannedByAdminId] = $this->scannerIdentity($scanner);

        $event = $this->recordEvent(
            $deposit,
            BagDepositEvent::TYPE_TEMP_ACCESS,
            $accessor->id,
            $scannedBy,
            $scannedByAdminId,
        );

        if ($accessor->id !== $deposit->user_id) {
            $this->notifyDepositor(
                $deposit,
                'bag_temp_access',
                'Bag deposit accessed',
                "Your bag deposit in slot {$deposit->slot->number} was opened temporarily by {$accessor->first_name} {$accessor->last_name} and returned.",
                ['bag_deposit_id' => $deposit->id, 'bag_deposit_event_id' => $event->id],
            );
        }

        return $event;
    }

    /**
     * Rescue claim: staff release with no QR and no claim code. The
     * collector's identity is established by a physical ID check plus a
     * mandatory written note; the release is flagged on the audit event.
     *
     * @throws RuntimeException on missing note, unverified ID, or closed deposit
     */
    public function rescueClaim(BagDeposit $deposit, string $note, bool $idVerified, User $scanner): BagDeposit
    {
        if (! $idVerified) {
            throw new RuntimeException('A physical ID check is required for a rescue release.');
        }

        if (Str::of($note)->trim()->isEmpty()) {
            throw new RuntimeException('A written note is required for a rescue release.');
        }

        // The release goes to the depositor — the person staff verified by ID.
        return $this->claimDeposit(
            $deposit,
            $deposit->user,
            BagDeposit::METHOD_RESCUE_LOOKUP,
            $scanner,
            $note,
            idVerified: true,
        );
    }

    /**
     * Notify the depositor through the app's standard in-app channel;
     * delivery failures never break a counter operation.
     */
    private function notifyDepositor(BagDeposit $deposit, string $category, string $title, string $message, array $data = []): void
    {
        try {
            app(NotificationService::class)->notify($deposit->user, $category, $title, $message, $data);
        } catch (\Throwable $e) {
            Log::error('Bag deposit notification failed: '.$e->getMessage());
        }
    }

    private function claimMethodLabel(string $method): string
    {
        return match ($method) {
            BagDeposit::METHOD_CLAIM_CODE => 'claim code',
            BagDeposit::METHOD_RESCUE_LOOKUP => 'staff rescue lookup (ID verified)',
            default => $method,
        };
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
