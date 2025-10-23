<?php

namespace App\Livewire\Pages\Student;

use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AttendanceQr extends Component
{
    // Use in-memory cache for the QR code SVG data
    private ?string $cachedQrCodePng = null; // TODO: Rename to cachedQrCodeSvg in future refactor

    public ?string $qrValidUntil = null;
    public ?int $activeSessionId = null;

    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     * Note: Excludes email and name as they are PII and not needed for validation
     * Uses user_id, timestamp, nonce, and user object for tamper detection
     */
    private function createCanonicalMessage(array $data): string
    {
        // Sort keys to ensure consistent ordering
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'timestamp' => $data['timestamp'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        // Create deterministic string representation
        return implode('|', [
            $fields['user_id'],
            $fields['timestamp'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }

    /**
     * Generate encrypted attendance data for QR code
     * Format: encrypted JSON with user_id, timestamp, and hash for tamper protection
     * Persists QR data based on active session to prevent unnecessary regeneration
     */
    private function generateAttendanceData(): string
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Check for active attendance session
        $activeSession = Attendance::getActiveSession($user->id);
        $this->activeSessionId = $activeSession?->id;

        // If there's an active session, try to reuse the cached QR data
        if ($activeSession) {
            $cacheKey = "qr_data:user:{$user->id}:session:{$activeSession->id}";
            $cachedData = Cache::get($cacheKey);

            if (
                is_array($cachedData)
                && array_key_exists('valid_until', $cachedData)
                && array_key_exists('encrypted_data', $cachedData)
                && is_string($cachedData['valid_until'])
                && is_string($cachedData['encrypted_data'])
            ) {
                // Update validity timestamp for display
                $this->qrValidUntil = Carbon::parse($cachedData['valid_until'])->toIso8601String();
                return $cachedData['encrypted_data'];
            }
        }

        $secret = config('app.qr_hmac_secret');
        if (!is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('QR HMAC secret is missing or insecure.');
        }

        $timestamp = Carbon::now()->timestamp;
        $validUntil = Carbon::now()->addHours(24); // QR valid for 24 hours

        // Generate unique nonce for replay attack protection
        $nonce = Str::random(32);

        // Build serializable user representation for QR payload
        $userPayload = [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];

        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash for additional tamper protection covering entire payload
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data to prevent tampering
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Cache the QR data if there's an active session
        if ($activeSession) {
            $cacheKey = "qr_data:user:{$user->id}:session:{$activeSession->id}";
            Cache::put($cacheKey, [
                'encrypted_data' => $encryptedData,
                'valid_until' => $validUntil->toIso8601String(),
            ], 86400); // Cache for 24 hours
        }

        $this->qrValidUntil = $validUntil->toIso8601String();

        return $encryptedData;
    }

    /**
     * Manually refresh the QR code (invalidate cache and generate new one)
     */
    public function refreshQrCode()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Clear cached QR data
        $activeSession = Attendance::getActiveSession($user->id);
        if ($activeSession) {
            $cacheKey = "qr_data:user:{$user->id}:session:{$activeSession->id}";
            Cache::forget($cacheKey);
        }

        // Clear in-memory cache
        $this->cachedQrCodePng = null;

        // Force re-render
        $this->dispatch('qr-refreshed');
    }

    /**
     * Generate QR code as SVG (centralized generator)
     * SVG format is more reliable for scanning and works better with Html5QrcodeScanner
     * 
     * @return string SVG data
     */
    private function generateQrCodeSvg(): string
    {
        // Return cached value if already generated in this request
        if ($this->cachedQrCodePng !== null) {
            return $this->cachedQrCodePng;
        }

        // Generate encrypted attendance data
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as SVG (more reliable for scanning than PNG)
        // Using simple generation like TestQrScanner for better compatibility
        $this->cachedQrCodePng = QrCode::size(300)->generate($attendanceData);

        return $this->cachedQrCodePng;
    }

    #[Computed]
    public function qrCodeDataUri(): string
    {
        // Delegate to centralized generator
        $svgData = $this->generateQrCodeSvg();

        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    /**
     * Download QR code as PNG file (better for printing and sharing)
     */
    public function downloadQrCode()
    {
        // Generate the encrypted data
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as PNG for download (better compatibility for printing)
        $pngData = QrCode::format('png')
            ->size(500)  // Larger size for better print quality
            ->errorCorrection('H')
            ->margin(2)
            ->generate($attendanceData);

        $fileName = 'attendance-qrcode-' . now()->format('Y-m-d-His') . '.png';
        $tempFilePath = 'temp/' . $fileName;

        // Ensure temp directory exists using Laravel Storage
        if (!Storage::exists('temp')) {
            Storage::makeDirectory('temp');
        }

        // Write PNG data using Laravel Storage
        Storage::put($tempFilePath, $pngData);

        // Get filesystem path for download response
        $fullPath = Storage::path($tempFilePath);

        // Return download response and delete file after sending
        return response()->download($fullPath, $fileName)->deleteFileAfterSend(true);
    }

    public function render()
    {
        return view('livewire.pages.student.attendance-qr');
    }
}
