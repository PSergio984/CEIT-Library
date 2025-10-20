<?php

namespace App\Livewire\Pages\Student;

use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class AttendanceQr extends Component
{
    public string $url = 'http://ceit-library.test/profile';

    // Use in-memory cache for the QR code
    private ?string $cachedQrCode = null;

    #[Computed]
    public function qrCodeDataUri(): string
    {
        // Return cached value if already generated in this request
        if ($this->cachedQrCode !== null) {
            return $this->cachedQrCode;
        }

        // Generate QR code as PNG data URI for better download support
        $qrCode = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($this->url);

        $this->cachedQrCode = 'data:image/png;base64,' . base64_encode($qrCode);

        return $this->cachedQrCode;
    }

    /**
     * Download QR code as PNG file
     */
    public function downloadQrCode()
    {
        // Generate QR code as PNG
        $qrCode = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->margin(1)
            ->generate($this->url);

        // Create a temporary file
        $fileName = 'attendance-qrcode.png';
        $tempPath = storage_path('app/temp/' . $fileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Save QR code to temp file
        file_put_contents($tempPath, $qrCode);

        // Return download response and delete file after sending
        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function render()
    {
        return view('livewire.pages.student.attendance-qr');
    }
}
