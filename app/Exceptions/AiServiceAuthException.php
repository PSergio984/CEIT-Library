<?php

namespace App\Exceptions;

use RuntimeException;

class AiServiceAuthException extends RuntimeException
{
    /**
     * Error taxonomy code surfaced to the widget (ADR 0004: 401 auth_failed).
     */
    public function errorCode(): string
    {
        return 'auth_failed';
    }
}
