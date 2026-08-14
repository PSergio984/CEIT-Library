<?php

namespace App\Exceptions;

class AiServiceProviderException extends AiServiceException
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
