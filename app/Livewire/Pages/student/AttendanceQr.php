<?php

namespace App\Livewire\Pages\Student;

use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;


class AttendanceQr extends Component
{
    public string $url = 'http://ceit-library.test/profile';

    // Use in-memory cache for the QR code PNG bytes
    private ?string $cachedQrCodePng = null;

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

        // Generate QR code as PNG with consistent parameters
        $this->cachedQrCodePng = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($this->url);

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
