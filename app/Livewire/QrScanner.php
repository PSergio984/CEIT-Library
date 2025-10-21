<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\User;
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
    use Toast;

    private const VALIDATION_EXPIRED = 'expired';
    private const VALIDATION_INVALID = 'invalid';
    private const VALIDATION_REPLAY = 'replay_attack';

    public bool $isScanning = false;
    public ?string $scannedData = null;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning', 'scannerError'];

    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     */
    private function createCanonicalMessage(array $data): string
    {
        // Sort keys to ensure consistent ordering
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'email' => $data['email'] ?? '',
            'name' => $data['name'] ?? '',
            'timestamp' => $data['timestamp'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        // Create deterministic string representation
        return implode('|', [
            $fields['user_id'],
            $fields['email'],
            $fields['name'],
            $fields['timestamp'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }

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
                $this->error('QR code expired. Please generate a new one.', 'Expired QR Code');
                $this->stopScanning();
                return;
            }
            if ($decryptedData === self::VALIDATION_REPLAY) {
                $this->error('This QR code has already been used. Please generate a new one.', 'Replay Attack Detected');
                $this->stopScanning();
                return;
            }
            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->error('Invalid or tampered QR code', 'Security Error');
                $this->stopScanning();
                return;
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);
            } else {
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
            $nonce = $data['nonce'];
            $cacheKey = 'qr_nonce:' . hash('sha256', $nonce);

            // Atomically mark nonce as used (Cache::add only succeeds if key doesn't exist)
            if (!Cache::add($cacheKey, true, 86400)) {
                Log::warning('Replay attack detected - nonce already used', [
                    'nonce_hash' => hash('sha256', $nonce),
                    'user_id' => $data['user_id'],
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
            $minPast = $now - (24 * 3600); // 24 hours ago
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

            if ($recentScans >= 5) { // Max 5 scans per minute
                Log::warning('Rate limit exceeded for user', [
                    'user_id' => $data['user_id'],
                    'scan_count' => $recentScans,
                ]);
                return self::VALIDATION_INVALID;
            }

            // Increment rate limit counter (1 minute TTL)
            Cache::put($rateLimitKey, $recentScans + 1, 60);

            // Nonce was atomically marked as used earlier using Cache::add()
            Log::info('QR code validated successfully, nonce marked as used', [
                'user_id' => $user->id,
                'nonce_hash' => hash('sha256', $nonce),
            ]);

            return [
                'user_id' => $data['user_id'],
                'timestamp' => $qrTimestamp,
                'user' => $user,
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

                    return [
                        'success' => true,
                        'message' => "Goodbye, {$user->first_name}! You stayed for {$activeSession->duration_minutes} minutes.",
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
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to complete check-out. Please try again.',
                    'title' => 'Check-out Error',
                ];
            }
        } else {
            // User is checking in (time in) - wrap in transaction
            try {
                $authUser = Auth::user();
                $scannerName = trim(($authUser?->first_name ?? 'Unknown') . ' ' . ($authUser?->last_name ?? ''));
                $notes = 'Scanned by ' . $scannerName;
                return DB::transaction(function () use ($userId, $scannedBy, $user, $notes) {
                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'time_in' => Carbon::now(),
                        'status' => 'active',
                        'scanned_by' => $scannedBy,
                        'notes' => $notes,
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
                    'message' => 'Failed to complete check-in. Please try again.',
                    'title' => 'Check-in Error',
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

    public function render()
    {
        return view('livewire.qr-scanner');
    }
}
