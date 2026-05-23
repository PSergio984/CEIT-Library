<?php

namespace App\Livewire;

use App\Traits\CreatesQrCanonicalMessage;
use App\Traits\ProcessesAttendanceQr;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class QrScanner extends Component
{
    use CreatesQrCanonicalMessage, ProcessesAttendanceQr, Toast;

    private const VALIDATION_INVALID = 'invalid';

    public bool $isScanning = false;

    public ?string $scannedData = null;

    public bool $hasError = false;

    // Listeners for parent components to control the scanner
    protected $listeners = ['startScanning', 'stopScanning', 'scannerError', 'handleFileUploadScan'];

    public function startScanning()
    {
        $this->isScanning = true;
        $this->scannedData = null;
        $this->hasError = false;
    }

    public function stopScanning()
    {
        $this->isScanning = false;
        // Dispatch browser event to ensure camera stops
        $this->dispatch('scanner-stopped');
    }

    public function handleScan(string $data)
    {
        $this->authorize('librarian-or-admin-access');

        try {
            // Basic validation
            $data = trim($data);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');
                $this->stopScanning();

                return;
            }

            // Decrypt and validate the attendance data
            $decryptedData = $this->decryptAndValidateAttendanceData($data);

            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->hasError = true;
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or network issues. Please try generating a new QR code.', 'Invalid QR Code');
                $this->stopScanning();

                return;
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                Log::info('Attendance recorded successfully', [
                    'user_id' => $decryptedData['user_id'],
                    'action' => $result['action'],
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);
            } else {
                // Attendance processing failed
                Log::warning('Attendance processing failed', [
                    'user_id' => $decryptedData['user_id'],
                    'error_title' => $result['title'],
                ]);

                $this->hasError = true;
                $this->error($result['message'], $result['title']);
            }

            $this->scannedData = $data;
            $this->stopScanning();
        } catch (\Exception $e) {
            Log::error('QR Scanner Error: '.$e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($data ?? ''),
            ]);

            $this->hasError = true;
            $this->error('An error occurred while processing the QR code', 'System Error');
            $this->stopScanning();
        }
    }

    public function scannerError($message, $title = 'Scanner Error', $skipToast = false)
    {
        $this->hasError = true;

        // Only show toast if not skipped (for inline errors we skip the toast)
        if (! $skipToast) {
            $this->error($message, $title);
        }
    }

    public function scannerWarning($message, $title = 'Warning')
    {
        $this->warning($message, $title);
    }

    public function handleFileUploadScan(string $data)
    {
        try {
            // Log the uploaded QR data for debugging
            Log::info('File upload scan initiated', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50).'...',
            ]);

            // Basic validation
            $data = trim($data);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');

                // Don't stop scanning immediately - let the error toast display
                return;
            }

            // Decrypt and validate the attendance data
            $decryptedData = $this->decryptAndValidateAttendanceData($data);

            if ($decryptedData === self::VALIDATION_INVALID) {
                $this->hasError = true;
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or network issues. Please try generating a new QR code.', 'Invalid QR Code');

                return;
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                Log::info('Attendance recorded successfully (file upload)', [
                    'user_id' => $decryptedData['user_id'],
                    'action' => $result['action'],
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);

                // Stop scanning after successful processing
                $this->stopScanning();
            } else {
                // Attendance processing failed
                Log::warning('Attendance processing failed (file upload)', [
                    'user_id' => $decryptedData['user_id'],
                    'error_title' => $result['title'],
                ]);

                $this->hasError = true;
                $this->error($result['message'], $result['title']);
                // Don't stop scanning on error - user can try a different QR code
            }

            $this->scannedData = $data;
        } catch (\Exception $e) {
            Log::error('QR File Upload Scanner Error: '.$e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($data ?? ''),
            ]);

            $this->hasError = true;
            $this->error('An error occurred while processing the QR code', 'System Error');
        }
    }

    public function render()
    {
        return view('livewire.qr-scanner');
    }
}
