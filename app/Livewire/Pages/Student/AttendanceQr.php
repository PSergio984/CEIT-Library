<?php

namespace App\Livewire\Pages\Student;

use App\Models\Attendance;
use App\Traits\CreatesQrCanonicalMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AttendanceQr extends Component
{
    use CreatesQrCanonicalMessage;

    // QR code generation version - increment when generation parameters change to invalidate cache
    // v5: Simplified QR - removed timestamp/expiration, QR codes are now permanent per user
    private const QR_CODE_VERSION = 'v5';

    // QR code generation settings for optimal scannability
    private const QR_SVG_SIZE = 400;      // Size for on-screen SVG display
    private const QR_PNG_SIZE = 800;      // Larger size for downloadable PNG
    private const QR_MARGIN = 8;          // Quiet zone margin (increased from 4 for better scanning)
    private const QR_ERROR_CORRECTION_SVG = 'M';  // Medium (15% recovery) - better for dense encrypted data
    private const QR_ERROR_CORRECTION_PNG = 'Q';  // Quartile (25%) for robust downloaded images

    // Use in-memory cache for the QR code SVG data
    private ?string $cachedQrCodeSvg = null;

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
     * Format: encrypted JSON with user_id, nonce, user info, and hash for tamper protection
     * QR code is permanent per user - no expiration, uses nonce for replay protection
     */
    private function generateAttendanceData(): string
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Use user-specific cache key (permanent QR per user)
        $cacheKey = 'qr_data:' . self::QR_CODE_VERSION . ":user:{$user->id}";

        // Try to reuse the cached QR data
        $cachedData = Cache::get($cacheKey);

        if (is_array($cachedData) && isset($cachedData['encrypted_data'])) {
            return $cachedData['encrypted_data'];
        }

        $secret = config('app.qr_hmac_secret');
        if (! is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('QR HMAC secret is missing or insecure.');
        }

        // Generate unique nonce for replay attack protection
        // This nonce allows the QR to be used twice (check-in and check-out)
        $nonce = Str::random(32);

        // Build serializable user representation for QR payload
        // Keep it minimal for better QR scanning reliability
        $userPayload = [
            'id' => $user->id,
            'email' => $user->email,
        ];

        $data = [
            'user_id' => $user->id,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash for tamper protection covering entire payload
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data to prevent tampering
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Cache permanently (no TTL - user can regenerate if needed by clearing cache)
        Cache::forever($cacheKey, [
            'encrypted_data' => $encryptedData,
            'created_at' => Carbon::now()->format('Y-m-d-His'),
        ]);

        return $encryptedData;
    }

    /**
     * Generate QR code as SVG (centralized generator)
     * SVG format is more reliable for scanning and works better with jsQR scanner
     *
     * @return string SVG data
     */
    private function generateQrCodeSvg(): string
    {
        // Return cached value if already generated in this request
        if ($this->cachedQrCodeSvg !== null) {
            return $this->cachedQrCodeSvg;
        }

        $user = Auth::user();
        if (! $user) {
            return '';
        }

        // Check for active session
        if ($this->activeSessionId === null) {
            $activeSession = Attendance::getActiveSession($user->id);
            $this->activeSessionId = $activeSession?->id;
        }

        // Use user-specific cache key for SVG
        $svgCacheKey = 'qr_svg:' . self::QR_CODE_VERSION . ":user:{$user->id}";

        $cachedSvg = Cache::get($svgCacheKey);

        if ($cachedSvg && is_string($cachedSvg)) {
            $this->cachedQrCodeSvg = $cachedSvg;

            return $cachedSvg;
        }

        // Generate SVG only if not cached
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as SVG with optimized settings for on-screen scanning
        $svg = QrCode::size(self::QR_SVG_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::QR_ERROR_CORRECTION_SVG)
            ->generate($attendanceData);

        // Cache permanently
        Cache::forever($svgCacheKey, $svg);

        $this->cachedQrCodeSvg = $svg;

        return $svg;
    }

    #[Computed(cache: true)]
    public function qrCodeDataUri(): string
    {
        $svgData = $this->generateQrCodeSvg();

        if (empty($svgData)) {
            return '';
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    /**
     * Regenerate QR code (clears cache and generates new nonce)
     * This should only be used if the user needs a fresh QR code
     * (e.g., after both check-in and check-out have been used)
     */
    public function regenerateQrCode()
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $version = self::QR_CODE_VERSION;

        // Clear all cached QR data for this user
        Cache::forget("qr_data:{$version}:user:{$user->id}");
        Cache::forget("qr_svg:{$version}:user:{$user->id}");
        Cache::forget("qr_png:{$version}:user:{$user->id}");

        // Clear in-memory cache
        $this->cachedQrCodeSvg = null;

        // Clear Livewire computed property cache
        unset($this->qrCodeDataUri);

        // Force re-render
        $this->dispatch('qr-regenerated');
    }

    /**
     * Download QR code as PNG file (better for printing and sharing)
     */
    public function downloadQrCode()
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Check cache for existing PNG
        $pngCacheKey = 'qr_png:' . self::QR_CODE_VERSION . ":user:{$user->id}";
        $cachedPng = Cache::get($pngCacheKey);

        if (is_array($cachedPng) && isset($cachedPng['data'], $cachedPng['created_at'])) {
            $pngData = base64_decode($cachedPng['data']);
            $createdAt = $cachedPng['created_at'];
        } else {
            // Generate PNG only if not cached
            $attendanceData = $this->generateAttendanceData();

            $pngData = QrCode::format('png')
                ->size(self::QR_PNG_SIZE)
                ->margin(self::QR_MARGIN)
                ->errorCorrection(self::QR_ERROR_CORRECTION_PNG)
                ->generate($attendanceData);

            $createdAt = Carbon::now()->format('Y-m-d-His');

            // Cache permanently
            Cache::forever($pngCacheKey, [
                'data' => base64_encode($pngData),
                'created_at' => $createdAt,
            ]);
        }

        $fileName = 'attendance-qrcode-' . $user->id . '.png';

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
