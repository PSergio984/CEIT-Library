<?php

namespace Tests\Traits;

trait TestHelper
{
    /**
     * Generate a unique session token for testing purposes.
     *
     * @param string|null $prefix Optional prefix for the token
     * @return string
     */
    protected function generateSessionToken(?string $prefix = null): string
    {
        $basePrefix = $prefix ?: 'test-token';
        return $basePrefix . '-' . uniqid();
    }

    /**
     * Generate multiple unique session tokens.
     *
     * @param int $count Number of tokens to generate
     * @param string|null $prefix Optional prefix for the tokens
     * @return array
     */
    protected function generateSessionTokens(int $count, ?string $prefix = null): array
    {
        $tokens = [];
        for ($i = 1; $i <= $count; $i++) {
            $tokens[] = $this->generateSessionToken($prefix ? "{$prefix}-{$i}" : "test-token-{$i}");
        }
        return $tokens;
    }
}
