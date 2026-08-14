<?php

namespace App\Exceptions;

class AiServiceAuthException extends AiServiceException
{
    /**
     * Error taxonomy code surfaced to the widget (ADR 0004: 401 auth_failed).
     */
    public function errorCode(): string
    {
        return 'auth_failed';
    }
}
