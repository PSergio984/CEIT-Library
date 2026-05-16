<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Notification;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class QrScanner extends Component
{
    use CreatesQrCanonicalMessage, Toast;

    private const VALIDATION_INVALID = 'invalid';

    public bool $isScanning = false;

    public ?string $scannedData = null;

    public bool $hasError = false;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning', 'scannerError', 'handleFileUploadScan'];

    public function startScanning()
    {
        $this->isScanning = true;
        $this->scannedData = null;
        $this->hasError = false;
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

            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->hasError = true;
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
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);
            } else {
                // Attendance processing failed
                Log::warning('Attendance processing failed', [
                    'user_id' => $decryptedData['user_id'],
                    'error_title' => $result['title'],
                ]);

                $this->hasError = true;
                $this->error($result['message'], $result['title']);
            }

            $this->scannedData = $data;
            $this->stopScanning();
        } catch (\Exception $e) {
            Log::error('QR Scanner Error: '.$e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($data ?? ''),
            ]);

            $this->hasError = true;
            $this->error('An error occurred while processing the QR code', 'System Error');
            $this->stopScanning();
        }
    }

    /**
     * Decrypt and validate the attendance QR code data
     * Updated to match v5 QR format (no timestamp, permanent QR codes)
     * Note: Nonce is used for HMAC integrity verification only, not for replay prevention.
     * The QR code is permanent and can be used unlimited times.
     *
     * @return array|string 'invalid' for validation failures, array for valid data
     */
    private function decryptAndValidateAttendanceData(string $encryptedData)
    {
        try {
            // Decrypt the data
            $decryptedJson = Crypt::decryptString($encryptedData);
            $data = json_decode($decryptedJson, true);

            // Validate HMAC secret
            $secret = config('app.qr_hmac_secret');
            if (! is_string($secret) || strlen($secret) < 16) {
                Log::error('QR HMAC secret missing or insecure');

                return self::VALIDATION_INVALID;
            }

            // Validate JSON structure
            // v5 required: user_id, user, hash, nonce
            // v6 required: user_id, hash, nonce (user removed for optimization)
            if (! is_array($data) || ! isset($data['user_id'], $data['hash'], $data['nonce'])) {
                Log::warning('Invalid QR code structure: Missing required fields', [
                    'data_keys' => array_keys($data ?? []),
                    'v' => $data['v'] ?? 'pre-v6',
                ]);

                return self::VALIDATION_INVALID;
            }

            // Verify hash for tamper protection covering entire payload
            // Remove hash from data before creating canonical message to avoid circular dependency
            $dataForCanonical = $data;
            unset($dataForCanonical['hash']);
            $canonicalMessage = $this->createCanonicalMessage($dataForCanonical);
            $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);

            if (! hash_equals($expectedHash, $data['hash'])) {
                Log::warning('QR code hash mismatch - possible tampering detected', [
                    'expected_prefix' => substr($expectedHash, 0, 8),
                    'received_prefix' => substr($data['hash'], 0, 8),
                    'v' => $data['v'] ?? 'pre-v6',
                    'user_id' => $data['user_id'] ?? 'unknown',
                ]);

                return self::VALIDATION_INVALID;
            }

            // Optional: Validate timestamp if present (allow for 10 min window for legacy codes)
            // This adds a layer of freshness check even if the code itself is reusable
            if (isset($data['timestamp'])) {
                $ageSeconds = now()->timestamp - $data['timestamp'];
                if ($ageSeconds < -60 || $ageSeconds > 900) { // Increased window for legacy
                    Log::debug('Legacy QR code timestamp skew', [
                        'age_seconds' => $ageSeconds,
                        'user_id' => $data['user_id'],
                    ]);
                }
            }

            $user = User::find($data['user_id']);
            if (! $user) {
                Log::warning('User not found during QR scan', ['user_id' => $data['user_id']]);

                return self::VALIDATION_INVALID;
            }

            // Check rate limiting per user (prevent rapid repeated scans)
            $rateLimitKey = 'qr_rate_limit:'.$data['user_id'];
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

            // QR code validated successfully - permanent QR codes have unlimited usage
            Log::info('QR code validated successfully', [
                'user_id' => $user->id,
            ]);

            return [
                'user_id' => $data['user_id'],
                'user' => $user,
            ];
        } catch (DecryptException $e) {
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

    /**
     * Process the attendance based on scanned QR data
     */
    private function processAttendance(array $data): array
    {
        $userId = $data['user_id'];
        $user = $data['user'];

        // Get the current user who is scanning
        $currentUser = Auth::user();

        // Get the librarian ID if current user has an active librarian duty
        // scanned_by must reference librarians.id, not users.id
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

    public function scannerError($message, $title = 'Scanner Error', $skipToast = false)
    {
        $this->hasError = true;

        // Only show toast if not skipped (for inline errors we skip the toast)
        if (! $skipToast) {
            $this->error($message, $title);
        }
    }

    public function scannerWarning($message, $title = 'Warning')
    {
        $this->warning($message, $title);
    }

    public function handleFileUploadScan(string $data)
    {
        try {
            // Log the uploaded QR data for debugging
            Log::info('File upload scan initiated', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50).'...',
            ]);

            // Basic validation
            $data = trim($data);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');

                // Don't stop scanning immediately - let the error toast display
                return;
            }

            // Decrypt and validate the attendance data
            $decryptedData = $this->decryptAndValidateAttendanceData($data);

            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->hasError = true;
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or network issues. Please try generating a new QR code.', 'Invalid QR Code');

                return;
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                Log::info('Attendance recorded successfully (file upload)', [
                    'user_id' => $decryptedData['user_id'],
                    'action' => $result['action'],
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);

                // Stop scanning after successful processing
                $this->stopScanning();
            } else {
                // Attendance processing failed
                Log::warning('Attendance processing failed (file upload)', [
                    'user_id' => $decryptedData['user_id'],
                    'error_title' => $result['title'],
                ]);

                $this->hasError = true;
                $this->error($result['message'], $result['title']);
                // Don't stop scanning on error - user can try a different QR code
            }

            $this->scannedData = $data;
        } catch (\Exception $e) {
            Log::error('QR File Upload Scanner Error: '.$e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($data ?? ''),
            ]);

            $this->hasError = true;
            $this->error('An error occurred while processing the QR code', 'System Error');
        }
    }

    public function render()
    {
        return view('livewire.qr-scanner');
    }
}
