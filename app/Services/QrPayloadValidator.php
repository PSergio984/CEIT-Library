<?php

namespace App\Services;

use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class QrPayloadValidator
{
    use CreatesQrCanonicalMessage;

    public const INVALID = 'invalid';

    /**
     * Decrypt and validate a QR code payload (v7)
     * Shared validation half: {"encrypted": ...} wrapper, HMAC signature,
     * required fields, timestamp skew and nonce replay protection.
     * Consumable by attendance scanning and other QR-based flows.
     *
     * @return array|string 'invalid' for validation failures, decrypted payload array for valid data
     */
    public function validate(string $qrData, bool $enforceTimestampSkew = true, bool $consumeNonce = true): array|string
    {
        try {
            // Basic validation
            $qrData = trim($qrData);
            if (empty($qrData)) {
                return self::INVALID;
            }

            // Standardized format: QR codes use a {"encrypted": "..."} wrapper
            $json = json_decode($qrData, true);
            if (! $json || ! isset($json['encrypted'])) {
                Log::warning('Attendance QR code missing encrypted wrapper');

                return self::INVALID;
            }

            // Decrypt the data
            $decryptedJson = Crypt::decryptString($json['encrypted']);
            $data = json_decode($decryptedJson, true);

            // Validate HMAC secret
            $secret = config('app.qr_hmac_secret');
            if (! is_string($secret) || strlen($secret) < 16) {
                Log::error('QR HMAC secret missing or insecure');

                return self::INVALID;
            }

            // Validate JSON structure (v7 required: user_id, hash, nonce, timestamp)
            if (! is_array($data) || ! isset($data['user_id'], $data['hash'], $data['nonce'], $data['timestamp'])) {
                Log::warning('Invalid QR code structure: Missing required fields', [
                    'data_keys' => array_keys($data ?? []),
                    'v' => $data['v'] ?? 'unknown',
                ]);

                return self::INVALID;
            }

            // Verify hash for tamper protection covering entire payload
            $dataForCanonical = $data;
            unset($dataForCanonical['hash']);
            $canonicalMessage = $this->createCanonicalMessage($dataForCanonical);
            $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);

            if (! hash_equals($expectedHash, $data['hash'])) {
                Log::warning('QR code hash mismatch - possible tampering detected', [
                    'user_id' => $data['user_id'],
                ]);

                return self::INVALID;
            }

            // Timestamp freshness (±60s clock drift allowed)
            if ($enforceTimestampSkew) {
                $timeDiff = time() - $data['timestamp'];

                if (abs($timeDiff) > 60) {
                    Log::warning('QR code rejected: Timestamp skew too high', [
                        'user_id' => $data['user_id'],
                        'time_diff' => $timeDiff,
                    ]);

                    return self::INVALID;
                }
            }

            // Nonce Replay Prevention (One-time use check)
            if ($consumeNonce) {
                $nonceKey = 'qr_nonce:'.$data['nonce'];
                if (! Cache::add($nonceKey, true, 150)) {
                    Log::warning('QR code rejected: Replay attack detected (nonce reuse)', [
                        'user_id' => $data['user_id'],
                        'nonce' => $data['nonce'],
                    ]);

                    return self::INVALID;
                }
            }

            return $data;
        } catch (DecryptException $e) {
            Log::warning('QR code decryption failed', ['error' => $e->getMessage()]);

            return self::INVALID;
        } catch (\Exception $e) {
            Log::error('Unexpected error during QR validation', ['error' => $e->getMessage()]);

            return self::INVALID;
        }
    }

    /**
     * Resolve a validated payload to its registered user: user lookup, per-user
     * scan rate limit, and nonce replay consumption. Shared tail used by
     * attendance, borrow and bag flows after validate(); each flow passes its
     * own nonce key prefix so a scan in one flow never burns another flow's QR.
     *
     * @return User|string 'invalid' on lookup/rate-limit/replay failure
     */
    public function resolveUser(array $data, string $noncePrefix = 'qr_nonce:'): User|string
    {
        $user = User::find($data['user_id']);
        if (! $user) {
            Log::warning('User not found during QR scan', ['user_id' => $data['user_id']]);

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
        $nonceKey = $noncePrefix.$data['nonce'];
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
}
