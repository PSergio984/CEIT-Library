<?php

namespace App\Livewire\Pages\Student;

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AttendanceQr extends Component
{
    public string $url = 'http://ceit-library.test/profile';

    public function render()
    {
        // Generate QR code as PNG data URI for better download support
        $qrCode = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($this->url);

        $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrCode);

        return view('livewire.pages.student.attendance-qr', [
            'qrCodeDataUri' => $qrCodeDataUri
        ]);
    }
}
