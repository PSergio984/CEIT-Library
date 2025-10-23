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
    private const QR_CACHE_TTL = 86400; // 24 hours in seconds
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
                && array_key_exists('created_at', $cachedData)
                && is_string($cachedData['valid_until'])
                && is_string($cachedData['encrypted_data'])
                && is_string($cachedData['created_at'])
            ) {
                $validUntilCarbon = Carbon::parse($cachedData['valid_until']);
                if ($validUntilCarbon->isFuture()) {
                    // Update validity timestamp for display
                    $this->qrValidUntil = $validUntilCarbon->toIso8601String();
                    return $cachedData['encrypted_data'];
                }
                // If expired, fall through to regenerate
            }
        }

        $secret = config('app.qr_hmac_secret');
        if (!is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('QR HMAC secret is missing or insecure.');
        }

        $timestamp = Carbon::now()->timestamp;
        $validUntil = Carbon::now()->addHours(24); // QR valid for 24 hours
        $createdAt = Carbon::now()->format('Y-m-d-His'); // Fixed creation timestamp for filename

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
                'created_at' => $createdAt,
            ], self::QR_CACHE_TTL); // Cache for 24 hours
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

        // Clear all cached QR data (data, PNG, and SVG)
        $activeSession = Attendance::getActiveSession($user->id);
        if ($activeSession) {
            $cacheKey = "qr_data:user:{$user->id}:session:{$activeSession->id}";
            $pngCacheKey = "qr_png:user:{$user->id}:session:{$activeSession->id}";
            $svgCacheKey = "qr_svg:user:{$user->id}:session:{$activeSession->id}";
            Cache::forget($cacheKey);
            Cache::forget($pngCacheKey);
            Cache::forget($svgCacheKey);
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

        $user = Auth::user();
        if (!$user) {
            return '';
        }

        // Check for active session to get SVG from cache
        $activeSession = Attendance::getActiveSession($user->id);
        $svgCacheKey = $activeSession ? "qr_svg:user:{$user->id}:session:{$activeSession->id}" : null;
        $cachedSvg = $svgCacheKey ? Cache::get($svgCacheKey) : null;

        if ($cachedSvg && is_string($cachedSvg)) {
            // Use cached SVG - no regeneration!
            $this->cachedQrCodePng = $cachedSvg;
            return $cachedSvg;
        }

        // Generate SVG only if not cached
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as SVG using SIMPLE generation like TestQrScanner
        // Simple QR codes are smaller, more scannable, and work reliably
        $svg = QrCode::size(300)->generate($attendanceData);

        // Cache the SVG for future renders (24 hours)
        if ($svgCacheKey) {
            Cache::put($svgCacheKey, $svg, self::QR_CACHE_TTL);
        }

        $this->cachedQrCodePng = $svg;
        return $svg;
    }

    #[Computed]
    public function qrCodeDataUri(): string
    {
        // Delegate to centralized generator
        $svgData = $this->generateQrCodeSvg();

        if (empty($svgData)) {
            return '';
        }

        // Use base64 encoding for reliable SVG data URI
        // This avoids UTF-8 encoding issues with rawurlencode
        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    /**
     * Check if refresh button should be disabled
     */
    #[Computed]
    public function canRefreshQr(): bool
    {
        // Disable refresh if there's an active session or QR hasn't expired
        if ($this->activeSessionId) {
            return false; // Active session - don't allow refresh
        }

        // Check if QR is still valid (hasn't timed out)
        if ($this->qrValidUntil) {
            $validUntil = Carbon::parse($this->qrValidUntil);
            if ($validUntil->isFuture()) {
                return false; // QR still valid - don't allow refresh
            }
        }

        return true; // Allow refresh
    }

    /**
     * Download QR code as PNG file (better for printing and sharing)
     * Uses the SAME cached QR data to prevent regeneration
     */
    public function downloadQrCode()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Check for active session to get cache key
        $activeSession = Attendance::getActiveSession($user->id);
        $cacheKey = $activeSession ? "qr_data:user:{$user->id}:session:{$activeSession->id}" : null;
        $cachedData = $cacheKey ? Cache::get($cacheKey) : null;

        // Get creation timestamp from cache, or use current time as fallback
        $createdAt = Carbon::now()->format('Y-m-d-His');
        if (is_array($cachedData) && isset($cachedData['created_at'])) {
            $createdAt = $cachedData['created_at'];
        }

        // Check if PNG is already cached to avoid regeneration
        $pngCacheKey = $activeSession ? "qr_png:user:{$user->id}:session:{$activeSession->id}" : null;
        $cachedPng = $pngCacheKey ? Cache::get($pngCacheKey) : null;

        if ($cachedPng && is_string($cachedPng)) {
            // Use cached PNG data - no regeneration!
            $pngData = $cachedPng;
        } else {
            // Generate PNG only if not cached
            $attendanceData = $this->generateAttendanceData();

            // Use SIMPLE generation like SVG for consistency
            $pngData = QrCode::format('png')
                ->size(600)  // Larger size for better scanning reliability
                ->generate($attendanceData);

            // Cache the PNG data for future downloads (24 hours)
            if ($pngCacheKey) {
                Cache::put($pngCacheKey, $pngData, self::QR_CACHE_TTL);
            }
        }

        // Use the original creation timestamp for consistent filename
        $fileName = 'attendance-qrcode-' . $createdAt . '.png';
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
