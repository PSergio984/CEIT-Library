<?php

namespace App\Livewire\Pages\Student;

use Livewire\Attributes\Computed;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

    public function render()
    {
        return view('livewire.pages.student.attendance-qr');
    }
}
