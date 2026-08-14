<?php

namespace App\Exceptions;

class AiServiceUnavailableException extends AiServiceException
{
    /**
     * Error taxonomy code surfaced to the widget (sidecar unreachable).
     */
    public function errorCode(): string
    {
        return 'unavailable';
    }
}
