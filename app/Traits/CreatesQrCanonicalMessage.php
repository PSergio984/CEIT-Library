<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait CreatesQrCanonicalMessage
{
    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     * Uses a deterministic approach for different payload versions
     */
    private function createCanonicalMessage(array $data): string
    {
        // Define the fixed order for canonical fields to ensure deterministic hashing
        // We only use essential fields for the signature
        $parts = [];

        // Essential core fields
        $parts[] = $data['user_id'] ?? ($data['id'] ?? '');
        $parts[] = $data['nonce'] ?? '';

        // Optional/Legacy fields (only include if present to support v5 and v6)
        if (isset($data['timestamp'])) {
            $parts[] = $data['timestamp'];
        }

        if (isset($data['user'])) {
            $userValue = is_array($data['user'])
                ? json_encode($data['user'], JSON_UNESCAPED_SLASHES)
                : (string) $data['user'];
            $parts[] = $userValue;
        }

        // Additional data fields (used for paper scans, etc.)
        if (isset($data['inventory_id'])) {
            $parts[] = $data['inventory_id'];
        }

        // Join with pipes to create the canonical string
        return implode('|', $parts);
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
