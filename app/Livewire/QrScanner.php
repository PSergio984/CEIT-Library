<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

    public bool $isScanning = false;
    public ?string $scannedData = null;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning', 'scannerError'];

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

            // Validate JSON structure
            if (!is_array($data) || !isset($data['user_id'], $data['timestamp'], $data['hash'], $data['email'], $data['name'])) {
                Log::warning('Invalid QR code structure', ['data_keys' => array_keys($data ?? [])]);
                return self::VALIDATION_INVALID;
            }

            // Validate timestamp (QR code shouldn't be older than 24 hours)
            $qrTimestamp = $data['timestamp'];
            $now = Carbon::now()->timestamp;
            $ageInHours = ($now - $qrTimestamp) / 3600;

            if ($ageInHours > 24) {
                Log::warning('QR code expired', ['age_hours' => $ageInHours]);
                return self::VALIDATION_EXPIRED;
            }

            // Verify hash for tamper protection
            $expectedHash = hash_hmac('sha256', $data['user_id'] . $qrTimestamp, config('app.qr_hmac_secret'));
            if (!hash_equals($expectedHash, $data['hash'])) {
                Log::warning('QR code hash mismatch - possible tampering detected');
                return self::VALIDATION_INVALID;
            }
            $user = User::find($data['user_id']);
            if (!$user) {
                Log::warning('User not found in QR code', ['user_id' => $data['user_id']]);
                return self::VALIDATION_INVALID;
            }
            return [
                'user_id' => $data['user_id'],
                'email' => $data['email'],
                'name' => $data['name'],
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
                $firstName = $authUser?->first_name ?? 'Unknown';
                $lastName = $authUser?->last_name ?? '';
                $notes = 'Scanned by ' . trim($firstName . ' ' . $lastName);
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
