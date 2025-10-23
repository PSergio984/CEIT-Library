<?php

namespace App\Traits;

trait CreatesQrCanonicalMessage
{
    /**
     * Create a canonical message for HMAC that covers all sensitive fields
     * Note: Excludes email and name as they are PII and not needed for validation
     * Uses user_id, timestamp, nonce, and user object for tamper detection
     */
    private function createCanonicalMessage(array $data): string
    {
        // Sort keys to ensure consistent ordering
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'timestamp' => $data['timestamp'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        // Create deterministic string representation
        return implode('|', [
            $fields['user_id'],
            $fields['timestamp'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }
}
