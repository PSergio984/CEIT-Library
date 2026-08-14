<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceProviderException extends RuntimeException
{
    /**
     * Error taxonomy code surfaced to the widget (ADR 0004: stream
     * `provider_error` event).
     */
    public function errorCode(): string
    {
        return 'provider_error';
    }
}
