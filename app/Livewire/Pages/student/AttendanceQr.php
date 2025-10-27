<?php

namespace App\Livewire\Pages\Student;

use App\Models\Attendance;
use App\Traits\CreatesQrCanonicalMessage;
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
    use CreatesQrCanonicalMessage;

    private const QR_CACHE_TTL = 86400; // 24 hours in seconds
    // Use in-memory cache for the QR code SVG data
    private ?string $cachedQrCodePng = null; // TODO: Rename to cachedQrCodeSvg in future refactor

    public ?string $qrValidUntil = null;
    public ?int $activeSessionId = null;

    /**
     * Initialize component and set active session ID early
     */
    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $activeSession = Attendance::getActiveSession($user->id);
            $this->activeSessionId = $activeSession?->id;
        }
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

        // Use session-specific cache key if available, otherwise fallback to user-only key
        // This ensures QR codes are cached even when there's no active session
        $cacheKey = $activeSession
            ? "qr_data:user:{$user->id}:session:{$activeSession->id}"
            : "qr_data:user:{$user->id}";

        // Try to reuse the cached QR data
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
        // Keep it minimal for better QR scanning reliability
        $userPayload = [
            'id' => $user->id,
            'email' => $user->email,
        ];

        $data = [
            'user_id' => $user->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash for additional tamper protection covering entire payload
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data to prevent tampering
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Always cache the QR data (use session-specific or user-only key)
        Cache::put($cacheKey, [
            'encrypted_data' => $encryptedData,
            'valid_until' => $validUntil->toIso8601String(),
            'created_at' => $createdAt,
        ], self::QR_CACHE_TTL); // Cache for 24 hours

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

        // Clear all cached QR data (data, PNG, and SVG) using both cache key patterns
        $activeSession = Attendance::getActiveSession($user->id);

        // Clear session-specific caches if session exists
        if ($activeSession) {
            Cache::forget("qr_data:user:{$user->id}:session:{$activeSession->id}");
            Cache::forget("qr_png:user:{$user->id}:session:{$activeSession->id}");
            Cache::forget("qr_svg:user:{$user->id}:session:{$activeSession->id}");
        }

        // Always clear user-only caches
        Cache::forget("qr_data:user:{$user->id}");
        Cache::forget("qr_png:user:{$user->id}");
        Cache::forget("qr_svg:user:{$user->id}");

        // Clear in-memory cache
        $this->cachedQrCodePng = null;

        // Reset activeSessionId to force re-check
        $this->activeSessionId = null;

        // Clear Livewire computed property cache
        unset($this->qrCodeDataUri);

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

        // Use activeSessionId if already set to avoid duplicate query
        if ($this->activeSessionId === null) {
            $activeSession = Attendance::getActiveSession($user->id);
            $this->activeSessionId = $activeSession?->id;
        }

        // Use session-specific cache key if available, otherwise fallback to user-only key
        $svgCacheKey = $this->activeSessionId
            ? "qr_svg:user:{$user->id}:session:{$this->activeSessionId}"
            : "qr_svg:user:{$user->id}";

        $cachedSvg = Cache::get($svgCacheKey);

        if ($cachedSvg && is_string($cachedSvg)) {
            // Use cached SVG - no regeneration!
            $this->cachedQrCodePng = $cachedSvg;
            return $cachedSvg;
        }

        // Generate SVG only if not cached
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as SVG with LOW error correction for simpler, more scannable codes
        // Low error correction = simpler patterns = easier to scan
        $svg = QrCode::size(300)
            ->errorCorrection('L')  // Low error correction for simplicity
            ->generate($attendanceData);

        // Always cache the SVG for future renders (24 hours)
        Cache::put($svgCacheKey, $svg, self::QR_CACHE_TTL);

        $this->cachedQrCodePng = $svg;
        return $svg;
    }

    #[Computed(cache: true)]
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
     * This is a regular computed property (not cached) so it updates dynamically
     */
    #[Computed]
    public function canRefreshQr(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Re-check active session dynamically (don't rely on cached property)
        $activeSession = Attendance::getActiveSession($user->id);

        // Disable refresh if there's an active session
        if ($activeSession) {
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

        // Check for active session to get cache key (fallback to user-only key)
        $activeSession = Attendance::getActiveSession($user->id);
        $cacheKey = $activeSession
            ? "qr_data:user:{$user->id}:session:{$activeSession->id}"
            : "qr_data:user:{$user->id}";

        $cachedData = Cache::get($cacheKey);

        // Get creation timestamp from cache, or use current time as fallback
        $createdAt = Carbon::now()->format('Y-m-d-His');
        if (is_array($cachedData) && isset($cachedData['created_at'])) {
            $createdAt = $cachedData['created_at'];
        }

        // Use session-specific or user-only cache key for PNG
        $pngCacheKey = $activeSession
            ? "qr_png:user:{$user->id}:session:{$activeSession->id}"
            : "qr_png:user:{$user->id}";

        $cachedPng = Cache::get($pngCacheKey);

        if (is_array($cachedPng) && isset($cachedPng['data']) && isset($cachedPng['encoded'])) {
            // Use cached PNG data with explicit encoding flag
            $pngData = $cachedPng['encoded'] ? base64_decode($cachedPng['data']) : $cachedPng['data'];
        } else {
            // Generate PNG only if not cached
            $attendanceData = $this->generateAttendanceData();

            // Use LOW error correction like SVG for consistency and simplicity
            $pngData = QrCode::format('png')
                ->size(600)  // Larger size for better scanning reliability
                ->errorCorrection('L')  // Low error correction for simpler patterns
                ->generate($attendanceData);

            // Cache the PNG data with explicit encoding flag to prevent ambiguity
            Cache::put($pngCacheKey, [
                'data' => base64_encode($pngData),
                'encoded' => true,
            ], self::QR_CACHE_TTL);
        }

        // Use the original creation timestamp for consistent filename
        $fileName = 'attendance-qrcode-' . $createdAt . '.png';

        // Return PNG data directly as download response without saving to disk
        return response()->streamDownload(function () use ($pngData) {
            echo $pngData;
        }, $fileName, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($pngData),
        ]);
    }

    public function render()
    {
        return view('livewire.pages.student.attendance-qr');
    }
}
