<?php

namespace App\Livewire;

use Livewire\Component;

class QrScanner extends Component
{
    public bool $isScanning = false;
    public ?string $scannedData = null;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning'];

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
        // Validate and sanitize the scanned data
        $data = trim($data);

        // Add validation based on your expected QR code format
        // Example: if expecting URLs only
        // if (!filter_var($data, FILTER_VALIDATE_URL)) {
        //     $this->addError('scannedData', 'Invalid QR code format');
        //     return;
        // }

        // Or length validation
        if (mb_strlen($data) > 500) {
            $this->addError('scannedData', 'QR code data too long');
            return;
        }

        $this->scannedData = $data;
        $this->isScanning = false;

        // Dispatch event to parent component with scanned data
        $this->dispatch('qrScanned', data: $data);
    }

    public function render()
    {
        return view('livewire.qr-scanner');
    }
}
