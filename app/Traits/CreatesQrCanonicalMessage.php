<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait CreatesQrCanonicalMessage
{
    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     * Uses a deterministic approach for different payload versions
     */
    protected function createCanonicalMessage(array $data): string
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
        // Support nested payload fields in v7
        if (isset($data['p']['inventory_id'])) {
            $parts[] = $data['p']['inventory_id'];
        } elseif (isset($data['inventory_id'])) {
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
     * Create encrypted canonical QR message for books (v7)
     * Includes nonce, timestamp and HMAC signature for replay protection
     */
    protected function createEncryptedQrMessage(array $borrowData): string
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $secret = config('app.qr_hmac_secret');

        if (! is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('QR HMAC secret is missing or insecure.');
        }

        // v7: Add nonce and timestamp for replay protection
        $data = [
            'v' => 7,
            'user_id' => $user?->id,
            'p' => $borrowData, // Payload (inventory_id, paper_id, etc.)
            'nonce' => \Illuminate\Support\Str::random(16),
            'timestamp' => time(),
        ];

        // Add hash for tamper protection covering entire payload
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the entire payload
        $encrypted = $this->encryptQrData($data);

        // Return as JSON with encrypted data
        return json_encode(['encrypted' => $encrypted]);
    }
}
