<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class QrScanner extends Component
{
    use Toast, CreatesQrCanonicalMessage;

    private const VALIDATION_EXPIRED = 'expired';
    private const VALIDATION_INVALID = 'invalid';
    private const VALIDATION_REPLAY = 'replay_attack';

    // QR code nonce TTL (24 hours in seconds)
    private const NONCE_TTL_SECONDS = 86400;

    public bool $isScanning = false;
    public ?string $scannedData = null;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning', 'scannerError', 'handleFileUploadScan'];

    public function startScanning()
    {
        $this->isScanning = true;
        $this->scannedData = null;
    }

    public function stopScanning()
    {
        $this->isScanning = false;
        // Dispatch browser event to ensure camera stops
        $this->dispatch('scanner-stopped');
    }

    public function handleScan(string $data)
    {
        try {
            // Basic validation
            $data = trim($data);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');
                $this->stopScanning();
                return;
            }

            // Decrypt and validate the attendance data
            $decryptedData = $this->decryptAndValidateAttendanceData($data);

            if ($decryptedData === self::VALIDATION_EXPIRED) {
                $this->error('QR code has expired. Please refresh the page and generate a new QR code.', 'QR Code Expired');
                $this->stopScanning();
                return;
            }
            if ($decryptedData === self::VALIDATION_REPLAY) {
                $this->error('This QR code has already been used twice (check-in and check-out). Please refresh the page to generate a new QR code.', 'QR Code Already Used');
                $this->stopScanning();
                return;
            }
            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or network issues. Please try generating a new QR code.', 'Invalid QR Code');
                $this->stopScanning();
                return;
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                Log::info('Attendance recorded successfully', [
                    'user_id' => $decryptedData['user_id'],
                    'action' => $result['action'],
                    'nonce_usage_count' => $decryptedData['current_usage_count'],
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);
            } else {
                // Attendance processing failed - nonce already consumed during validation
                // User cannot retry with this QR code
                Log::warning('Attendance processing failed, nonce already consumed', [
                    'user_id' => $decryptedData['user_id'],
                    'error_title' => $result['title'],
                    'nonce_usage_count' => $decryptedData['current_usage_count'],
                ]);

                $this->error($result['message'], $result['title']);
            }

            $this->scannedData = $data;
            $this->stopScanning();
        } catch (\Exception $e) {
            Log::error('QR Scanner Error: ' . $e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($data ?? ''),
            ]);

            $this->error('An error occurred while processing the QR code', 'System Error');
            $this->stopScanning();
        }
    }

    /**
     * Decrypt and validate the attendance QR code data
     */
    /**
     * @return array|null|string 'expired' if expired, null for other failures, array for valid data
     */
    private function decryptAndValidateAttendanceData(string $encryptedData)
    {
        try {
            // Decrypt the data
            $decryptedJson = Crypt::decryptString($encryptedData);
            $data = json_decode($decryptedJson, true);

            // Validate HMAC secret
            $secret = config('app.qr_hmac_secret');
            if (!is_string($secret) || strlen($secret) < 16) {
                Log::error('QR HMAC secret missing or insecure');
                return self::VALIDATION_INVALID;
            }

            // Validate JSON structure (only require user_id, timestamp, user, hash, nonce)
            if (!is_array($data) || !isset($data['user_id'], $data['timestamp'], $data['user'], $data['hash'], $data['nonce'])) {
                Log::warning('Invalid QR code structure', ['data_keys' => array_keys($data ?? [])]);
                return self::VALIDATION_INVALID;
            }

            // Check for replay attack using nonce
            // Allow TWO uses per nonce: one for check-in, one for check-out
            $nonce = $data['nonce'];
            $cacheKey = 'qr_nonce:' . hash('sha256', $nonce);

            // Atomically initialize the nonce counter with TTL if it doesn't exist
            // Cache::add() only sets the value if the key doesn't exist (atomic operation)
            Cache::add($cacheKey, 0, self::NONCE_TTL_SECONDS);

            // Atomically increment the usage count
            $usageCount = Cache::increment($cacheKey, 1);

            // Check if exceeded limit
            if ($usageCount > 2) {
                // QR code already used twice (check-in and check-out)
                // Don't decrement - keep the accurate count for logging/monitoring
                Log::warning('QR code exhausted - already used for check-in and check-out', [
                    'nonce_hash' => hash('sha256', $nonce),
                    'user_id' => $data['user_id'],
                    'usage_count' => $usageCount,
                ]);
                return self::VALIDATION_REPLAY;
            }

            // Validate timestamp with clock-skew tolerance
            $qrTimestamp = $data['timestamp'];
            if (!is_numeric($qrTimestamp)) {
                Log::warning('QR code timestamp is not numeric', ['timestamp' => $qrTimestamp]);
                return self::VALIDATION_INVALID;
            }
            $qrTimestamp = (int)$qrTimestamp;
            $now = Carbon::now()->timestamp;
            $maxFuture = $now + 600; // 10 minutes in future
            $minPast = $now - self::NONCE_TTL_SECONDS; // 24 hours ago
            $minAllowed = $now - 600; // 10 minutes in past

            if ($qrTimestamp < $minPast || $qrTimestamp > $maxFuture) {
                Log::warning('QR code expired or timestamp out of bounds', [
                    'qrTimestamp' => $qrTimestamp,
                    'now' => $now,
                    'minPast' => $minPast,
                    'maxFuture' => $maxFuture,
                ]);
                return self::VALIDATION_EXPIRED;
            }
            if ($qrTimestamp < $minAllowed) {
                Log::info('QR code timestamp is older than allowed clock-skew tolerance', [
                    'qrTimestamp' => $qrTimestamp,
                    'now' => $now,
                    'minAllowed' => $minAllowed,
                ]);
                // Still allow, but log info
            }

            // Verify hash for tamper protection covering entire payload
            $canonicalMessage = $this->createCanonicalMessage($data);
            $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);
            if (!hash_equals($expectedHash, $data['hash'])) {
                Log::warning('QR code hash mismatch - possible tampering detected', [
                    'expected' => substr($expectedHash, 0, 16),
                    'received' => substr($data['hash'], 0, 16),
                ]);
                return self::VALIDATION_INVALID;
            }


            $user = User::find($data['user_id']);
            if (!$user) {
                Log::warning('User not found in QR code', ['user_id' => $data['user_id']]);
                return self::VALIDATION_INVALID;
            }

            // Check rate limiting per user (prevent rapid repeated scans)
            $rateLimitKey = 'qr_rate_limit:' . $data['user_id'];
            $recentScans = Cache::get($rateLimitKey, 0);

            if ($recentScans >= 60) { // Max 60 scans per minute for testing
                Log::warning('Rate limit exceeded for user', [
                    'user_id' => $data['user_id'],
                    'scan_count' => $recentScans,
                ]);
                return self::VALIDATION_INVALID;
            }

            // Increment rate limit counter (1 minute TTL)
            Cache::put($rateLimitKey, $recentScans + 1, 60);

            // Nonce already atomically incremented during validation check
            // If attendance processing fails later, the nonce usage is already consumed
            // This is acceptable as it prevents TOCTOU race conditions
            Log::info('QR code validated successfully, nonce usage incremented', [
                'user_id' => $user->id,
                'nonce_hash' => hash('sha256', $nonce),
                'current_usage_count' => $usageCount,
                'remaining_uses' => 2 - $usageCount,
            ]);

            return [
                'user_id' => $data['user_id'],
                'timestamp' => $qrTimestamp,
                'user' => $user,
                'nonce_cache_key' => $cacheKey,
                'current_usage_count' => $usageCount,
            ];
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::warning('QR code decryption failed - possible tampering', ['error' => $e->getMessage()]);
            return self::VALIDATION_INVALID;
        } catch (\Exception $e) {
            Log::error('Unexpected error during QR validation', ['error' => $e->getMessage()]);
            return self::VALIDATION_INVALID;
        }
    }

    /**
     * Format duration in a human-readable way
     * Handles edge cases like < 1 minute
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return 'less than 1 minute';
        } elseif ($minutes < 60) {
            return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        } else {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            $hoursText = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
            if ($remainingMinutes > 0) {
                return $hoursText . ' and ' . $remainingMinutes . ' ' . ($remainingMinutes === 1 ? 'minute' : 'minutes');
            }
            return $hoursText;
        }
    }

    /**
     * Process the attendance based on scanned QR data
     */
    private function processAttendance(array $data): array
    {
        $userId = $data['user_id'];
        $user = $data['user'];
        $scannedBy = Auth::id(); // Current librarian/admin

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
                    $minutes = (int)$activeSession->duration_minutes;
                    $durationText = $this->formatDuration($minutes);

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
                    'user_id' => $userId,
                    'attendance_id' => $activeSession->id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-out: {$e->getMessage()}. Please contact the librarian for assistance.",
                    'title' => 'Check-out Failed',
                ];
            }
        } else {
            // User is checking in (time in) - wrap in transaction
            try {
                return DB::transaction(function () use ($userId, $scannedBy, $user) {
                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'time_in' => Carbon::now(),
                        'status' => 'active',
                        'scanned_by' => $scannedBy,
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
                    'exception' => $e,
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-in: {$e->getMessage()}. Please contact the librarian for assistance.",
                    'title' => 'Check-in Failed',
                ];
            }
        }
    }

    public function scannerError($message, $title = 'Scanner Error')
    {
        $this->error($message, $title);
    }

    public function scannerWarning($message, $title = 'Warning')
    {
        $this->warning($message, $title);
    }

    public function handleFileUploadScan(string $data)
    {
        // Log the uploaded QR data for debugging
        Log::info('File upload scan initiated', [
            'data_length' => strlen($data),
            'data_preview' => substr($data, 0, 50) . '...',
        ]);

        // Use the same handleScan logic
        $this->handleScan($data);
    }

    public function render()
    {
        return view('livewire.qr-scanner');
    }
}
