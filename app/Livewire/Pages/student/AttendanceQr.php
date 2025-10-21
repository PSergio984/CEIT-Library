<?php

namespace App\Livewire\Pages\Student;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AttendanceQr extends Component
{
    // Use in-memory cache for the QR code PNG bytes
    private ?string $cachedQrCodePng = null;

    /**
     * Generate encrypted attendance data for QR code
     * Format: encrypted JSON with user_id, timestamp, and hash for tamper protection
     */
    private function generateAttendanceData(): string
    {
        $user = Auth::user();
        $timestamp = Carbon::now()->timestamp;

        // Create data array with user info and timestamp
        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'timestamp' => $timestamp,
            // Add hash for additional tamper protection
            'hash' => hash_hmac('sha256', $user->id . $timestamp, config('app.qr_hmac_secret'))
        ];

        // Encrypt the data to prevent tampering
        $encryptedData = Crypt::encryptString(json_encode($data));

        return $encryptedData;
    }

    /**
     * Generate QR code as PNG bytes (centralized generator)
     * 
     * @return string PNG binary data
     */
    private function generateQrCodePng(): string
    {
        // Return cached value if already generated in this request
        if ($this->cachedQrCodePng !== null) {
            return $this->cachedQrCodePng;
        }

        // Generate encrypted attendance data
        $attendanceData = $this->generateAttendanceData();

        // Generate QR code as PNG with consistent parameters
        $this->cachedQrCodePng = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($attendanceData);

        return $this->cachedQrCodePng;
    }

    #[Computed]
    public function qrCodeDataUri(): string
    {
        // Delegate to centralized generator
        $pngBytes = $this->generateQrCodePng();

        return 'data:image/png;base64,' . base64_encode($pngBytes);
    }

    /**
     * Download QR code as PNG file
     */
    public function downloadQrCode()
    {
        // Use centralized generator
        $pngBytes = $this->generateQrCodePng();

        $fileName = 'attendance-qrcode.png';
        $tempFilePath = 'temp/' . $fileName;

        // Ensure temp directory exists using Laravel Storage
        if (!Storage::exists('temp')) {
            Storage::makeDirectory('temp');
        }

        // Write PNG bytes using Laravel Storage
        Storage::put($tempFilePath, $pngBytes);

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
