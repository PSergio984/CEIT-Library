<?php

namespace App\Traits;

use App\Models\Attendance;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ProcessesAttendanceQr
{
    /**
     * Decrypt and validate the attendance QR code data
     * Updated to v7 format: dynamic timestamps and nonce replay protection
     *
     * @return array|string 'invalid' for validation failures, array for valid data
     */
    protected function decryptAndValidateAttendanceData(string $qrData)
    {
        try {
            // Basic validation
            $qrData = trim($qrData);
            if (empty($qrData)) {
                return 'invalid';
            }

            // Standardize format: Attendance QR now uses {"encrypted": "..."} just like Borrow QR
            $json = json_decode($qrData, true);
            if (! $json || ! isset($json['encrypted'])) {
                Log::warning('Attendance QR code missing encrypted wrapper');

                return 'invalid';
            }

            // Decrypt the data
            $decryptedJson = Crypt::decryptString($json['encrypted']);
            $data = json_decode($decryptedJson, true);

            // Validate HMAC secret
            $secret = config('app.qr_hmac_secret');
            if (! is_string($secret) || strlen($secret) < 16) {
                Log::error('QR HMAC secret missing or insecure');

                return 'invalid';
            }

            // Validate JSON structure (v7 required: user_id, hash, nonce, timestamp)
            if (! is_array($data) || ! isset($data['user_id'], $data['hash'], $data['nonce'], $data['timestamp'])) {
                Log::warning('Invalid QR code structure: Missing required fields', [
                    'data_keys' => array_keys($data ?? []),
                    'v' => $data['v'] ?? 'unknown',
                ]);

                return 'invalid';
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

                return 'invalid';
            }

            // --- REPLAY ATTACK PROTECTION (Wave 3) ---

            // 1. Validate timestamp freshness (±60s clock drift allowed, 30s expiration target)
            $serverTime = time();
            $qrTime = $data['timestamp'];
            $timeDiff = $serverTime - $qrTime;

            if (abs($timeDiff) > 60) {
                Log::warning('QR code rejected: Timestamp skew too high', [
                    'user_id' => $data['user_id'],
                    'time_diff' => $timeDiff,
                ]);

                return 'invalid';
            }

            // 2. Nonce Replay Prevention (One-time use check)
            $nonceKey = 'qr_nonce:'.$data['nonce'];
            if (Cache::has($nonceKey)) {
                Log::warning('QR code rejected: Replay attack detected (nonce reuse)', [
                    'user_id' => $data['user_id'],
                    'nonce' => $data['nonce'],
                ]);

                return 'invalid';
            }

            // Store nonce in cache for 150 seconds (longer than QR validity window) to block reuse
            Cache::put($nonceKey, true, 150);

            // --- END REPLAY PROTECTION ---

            $user = User::find($data['user_id']);
            if (! $user) {
                Log::warning('User not found during QR scan', ['user_id' => $data['user_id']]);

                return 'invalid';
            }

            // Check rate limiting per user
            $rateLimitKey = 'qr_rate_limit:'.$data['user_id'];
            $recentScans = Cache::get($rateLimitKey, 0);

            if ($recentScans >= 60) {
                Log::warning('Rate limit exceeded for user', [
                    'user_id' => $data['user_id'],
                ]);

                return 'invalid';
            }

            Cache::put($rateLimitKey, $recentScans + 1, 60);

            Log::info('QR code validated successfully (v7)', [
                'user_id' => $user->id,
            ]);

            return [
                'user_id' => $data['user_id'],
                'user' => $user,
            ];
        } catch (DecryptException $e) {
            Log::warning('QR code decryption failed', ['error' => $e->getMessage()]);

            return 'invalid';
        } catch (\Exception $e) {
            Log::error('Unexpected error during QR validation', ['error' => $e->getMessage()]);

            return 'invalid';
        }
    }

    /**
     * Process the attendance based on scanned QR data
     */
    protected function processAttendance(array $data): array
    {
        $userId = $data['user_id'];
        $user = $data['user'];

        // Get the current user who is scanning
        $currentUser = Auth::user();

        // Get the librarian ID if current user has an active librarian duty
        $scannedBy = $currentUser?->getActiveLibrarianDuty()?->id;

        // If no librarian duty but user has admin access, store admin user ID
        $scannedByAdminId = null;
        if (! $scannedBy && $currentUser?->hasAdminAccess()) {
            $scannedByAdminId = $currentUser->id;
        }

        // Check if user has an active session
        $activeSession = Attendance::getActiveSession($userId);

        if ($activeSession) {
            // User is checking out (time out) - wrap in transaction
            try {
                return DB::transaction(function () use ($activeSession, $user) {
                    $activeSession->time_out = Carbon::now();
                    $activeSession->status = 'completed';
                    $activeSession->calculateDuration();
                    $activeSession->save();

                    // Format duration message properly
                    $minutes = (int) $activeSession->duration_minutes;
                    $durationText = $this->formatDuration($minutes);

                    // Create check-out notification for the user
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'attendance_checkout',
                        'title' => 'Library Check-out Successful',
                        'message' => "You checked out of the library. Total time: {$durationText}. Thank you for visiting!",
                        'data' => [
                            'attendance_id' => $activeSession->id,
                            'time_in' => $activeSession->time_in->format('M d, Y h:i A'),
                            'time_out' => $activeSession->time_out->format('M d, Y h:i A'),
                            'duration_minutes' => $minutes,
                            'duration_text' => $durationText,
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => "Goodbye, {$user->first_name}! You stayed for {$durationText}.",
                        'title' => 'Check-out Successful',
                        'attendance' => $activeSession,
                        'action' => 'checkout',
                    ];
                });
            } catch (\Exception $e) {
                Log::error('Check-out transaction failed', [
                    'user_id' => $activeSession->user_id,
                    'attendance_id' => $activeSession->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-out: {$e->getMessage()}",
                    'title' => 'Check-out Failed',
                ];
            }
        } else {
            // User is checking in (time in) - wrap in transaction
            try {
                return DB::transaction(function () use ($userId, $scannedBy, $scannedByAdminId, $user) {
                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'role_id' => $user->role_id,
                        'time_in' => Carbon::now(),
                        'status' => 'active',
                        'scanned_by' => $scannedBy,
                        'scanned_by_admin_id' => $scannedByAdminId,
                    ]);

                    // Create check-in notification for the user
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'attendance_checkin',
                        'title' => 'Library Check-in Successful',
                        'message' => "Welcome to the library! You checked in at {$attendance->time_in->format('h:i A')}. Enjoy your time!",
                        'data' => [
                            'attendance_id' => $attendance->id,
                            'time_in' => $attendance->time_in->format('M d, Y h:i A'),
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => "Welcome, {$user->first_name}! Enjoy your time in the library.",
                        'title' => 'Check-in Successful',
                        'attendance' => $attendance,
                        'action' => 'checkin',
                    ];
                });
            } catch (\Exception $e) {
                Log::error('Check-in transaction failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-in: {$e->getMessage()}",
                    'title' => 'Check-in Failed',
                ];
            }
        }
    }

    /**
     * Format duration in a human-readable way
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return 'less than 1 minute';
        } elseif ($minutes < 60) {
            return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        } else {
            $hours = (int) floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            $hoursText = $hours.' '.($hours === 1 ? 'hour' : 'hours');
            if ($remainingMinutes > 0) {
                return $hoursText.' and '.$remainingMinutes.' '.($remainingMinutes === 1 ? 'minute' : 'minutes');
            }

            return $hoursText;
        }
    }
}
