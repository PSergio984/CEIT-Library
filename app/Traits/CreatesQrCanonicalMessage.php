<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait CreatesQrCanonicalMessage
{
    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     * Note: Excludes email and name as they are PII and not needed for validation
     * Uses user_id, nonce, and user object for tamper detection
     * Timestamp removed in v5 - QR codes no longer expire based on time
     */
    private function createCanonicalMessage(array $data): string
    {
        // Sort keys to ensure consistent ordering
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        // Create deterministic string representation
        return implode('|', [
            $fields['user_id'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }

    /**
     * Encrypt data for QR code
     */
    protected function encryptQrData(array $data): string
    {
        try {
            $json = json_encode($data);
            $encrypted = Crypt::encryptString($json);

            return $encrypted;
        } catch (\Exception $e) {
            \Log::error('QR Encryption Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Decrypt QR code data
     */
    protected function decryptQrData(string $encryptedData): ?array
    {
        try {
            $decrypted = Crypt::decryptString($encryptedData);
            $data = json_decode($decrypted, true);

            return $data;
        } catch (\Exception $e) {
            \Log::error('QR Decryption Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create encrypted canonical QR message
     */
    protected function createEncryptedQrMessage(array $borrowData): string
    {
        // Wrap borrow data in 'p' key structure
        $payload = ['p' => $borrowData];

        // Encrypt the entire payload
        $encrypted = $this->encryptQrData($payload);

        // Return as JSON with encrypted data
        return json_encode(['encrypted' => $encrypted]);
    }
}
