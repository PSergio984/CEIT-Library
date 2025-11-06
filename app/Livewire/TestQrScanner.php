<?php

namespace App\Livewire;

use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use Mary\Traits\Toast;

class TestQrScanner extends Component
{
    use Toast, CreatesQrCanonicalMessage;

    public $testQrCode = null;
    public $testQrData = null;
    public $lastScanResult = null;
    public $validationResult = null;
    public bool $isScanning = false;

    protected $listeners = ['startScanning', 'stopScanning', 'handleScanTest', 'handleFileUploadScan'];

    public function generateTestQr()
    {
        $user = Auth::user();
        if (!$user) {
            $this->error('You must be logged in to generate a test QR code');
            return;
        }

        $secret = config('app.qr_hmac_secret');
        if (!is_string($secret) || strlen($secret) < 16) {
            $this->error('QR HMAC secret is not configured properly');
            return;
        }

        $timestamp = Carbon::now()->timestamp;

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

        // Add HMAC hash covering the entire payload
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Store for display
        $this->testQrData = substr($encryptedData, 0, 100) . '... (' . strlen($encryptedData) . ' chars total)';

        // Generate QR code
        try {
            $qrCodeSvg = QrCode::size(300)
                ->generate($encryptedData);
            $this->testQrCode = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);
        } catch (\Exception $e) {
            $this->testQrCode = null;
            $this->error('Failed to generate QR code: ' . $e->getMessage());
            return;
        }

        $this->success('Test QR code generated successfully!');
    }

    public function openScanner()
    {
        $this->isScanning = true;
        $this->dispatch('startScanning');
    }

    public function stopScanning()
    {
        $this->isScanning = false;
        $this->dispatch('scanner-stopped');
    }

    public function handleFileUploadScan(string $data)
    {
        // Use the same validation logic as handleScanTest
        $this->handleScanTest($data);
        $this->stopScanning();
    }

    public function scannerError($message, $title = 'Scanner Error')
    {
        $this->error($message, $title);
    }

    public function handleScanTest($data)
    {
        $this->lastScanResult = substr($data, 0, 100) . '... (' . strlen($data) . ' chars total)';

        try {
            // Decrypt and validate
            $decryptedJson = Crypt::decryptString($data);
            $decoded = json_decode($decryptedJson, true);

            $secret = config('app.qr_hmac_secret');

            // Validate structure

            $ageSeconds = isset($decoded['timestamp']) ? (Carbon::now()->timestamp - $decoded['timestamp']) : null;
            $timestampValid = isset($decoded['timestamp']) && $ageSeconds !== null && $ageSeconds >= 0 && $ageSeconds <= 600;

            $validation = [
                'decryption' => 'SUCCESS',
                'structure' => [
                    'has_user_id' => isset($decoded['user_id']),
                    'has_timestamp' => isset($decoded['timestamp']),
                    'has_user' => isset($decoded['user']),
                    'has_hash' => isset($decoded['hash']),
                    'has_nonce' => isset($decoded['nonce']),
                ],
                'timestamp' => [
                    'value' => $decoded['timestamp'] ?? null,
                    'readable' => isset($decoded['timestamp']) ? Carbon::createFromTimestamp($decoded['timestamp'])->toDateTimeString() : null,
                    'age_seconds' => $ageSeconds,
                    'valid' => $timestampValid,
                ],
                'nonce' => [
                    'present' => isset($decoded['nonce']),
                    'length' => isset($decoded['nonce']) ? strlen($decoded['nonce']) : 0,
                    'hash' => isset($decoded['nonce']) ? hash('sha256', $decoded['nonce']) : null,
                ],
                'hmac' => 'CHECKING...',
                'data' => $decoded,
            ];

            // Timestamp validation: reject if missing, future, or expired
            if (!isset($decoded['timestamp']) || $ageSeconds === null) {
                $this->validationResult = [
                    'error' => 'QR code timestamp missing or unreadable.',
                    'details' => $validation,
                ];
                $this->error('Validation failed: QR code timestamp missing or unreadable.');
                return;
            }
            if ($ageSeconds < 0) {
                $this->validationResult = [
                    'error' => 'QR code timestamp is in the future (invalid).',
                    'details' => $validation,
                ];
                $this->error('Validation failed: QR code timestamp is in the future (invalid).');
                return;
            }
            if ($ageSeconds > 600) {
                $this->validationResult = [
                    'error' => 'QR code expired (timestamp too old).',
                    'details' => $validation,
                ];
                $this->error('Validation failed: QR code expired (timestamp too old).');
                return;
            }

            // Verify HMAC covering entire payload
            if (isset($decoded['user_id'], $decoded['timestamp'], $decoded['hash'], $decoded['nonce'])) {
                $canonicalMessage = $this->createCanonicalMessage($decoded);
                $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);
                $validation['hmac'] = hash_equals($expectedHash, $decoded['hash']) ? 'VALID' : 'INVALID';
                $validation['hmac_details'] = [
                    'expected' => substr($expectedHash, 0, 16) . '...',
                    'received' => substr($decoded['hash'], 0, 16) . '...',
                    'canonical_message' => substr($canonicalMessage, 0, 100) . '...',
                ];
            }

            $this->validationResult = $validation;
            $this->success('QR code validated successfully!');
        } catch (\Exception $e) {
            $this->validationResult = [
                'error' => $e->getMessage(),
            ];
            $this->error('Validation failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.test-qr-scanner');
    }
}
