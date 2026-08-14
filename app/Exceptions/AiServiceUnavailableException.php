<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceUnavailableException extends RuntimeException
{
    /**
     * Error taxonomy code surfaced to the widget (sidecar unreachable).
     */
    public function errorCode(): string
    {
        return 'unavailable';
    }
}
