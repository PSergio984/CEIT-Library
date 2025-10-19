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

    public function handleScan($data)
    {
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
