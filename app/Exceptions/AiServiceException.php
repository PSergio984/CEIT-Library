<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for every typed AiService failure. Carries the ADR 0004 error
 * taxonomy code the widget surfaces; a concrete subclass exists per code
 * (auth_failed / unavailable / provider_error), so callers can catch the
 * base and rely on errorCode() without instanceof chains.
 */
abstract class AiServiceException extends RuntimeException
{
    /**
     * Error taxonomy code surfaced to the widget (ADR 0004).
     */
    abstract public function errorCode(): string;
}
