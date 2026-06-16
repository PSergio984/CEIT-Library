<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class PiiSanitizerProcessor implements ProcessorInterface
{
    /**
     * Keys in the context array that should be redacted
     */
    protected array $redactKeys = [
        'email',
        'password',
        'password_confirmation',
        'encrypted',
        'token',
        'session_token',
        'hash',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        // Recursively sanitize the context array
        $sanitizedContext = $this->sanitizeArray($context);

        // Sanitize the message string for potential email addresses or raw JSON payloads
        $sanitizedMessage = $this->sanitizeMessage($record->message);

        return $record->with(
            message: $sanitizedMessage,
            context: $sanitizedContext
        );
    }

    /**
     * Recursively sanitize array values based on keys
     */
    protected function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($key) && in_array(strtolower($key), $this->redactKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeMessage($value);
            }
        }

        return $data;
    }

    /**
     * Mask emails and attempt to redact JSON structures within strings
     */
    protected function sanitizeMessage(string $message): string
    {
        // Redact email addresses
        $message = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[EMAIL REDACTED]',
            $message
        );

        // Simple attempt to catch embedded JSON that looks like our encrypted QR payload
        if (str_contains($message, '"encrypted":')) {
            $message = preg_replace(
                '/"encrypted"\s*:\s*"[^"]*"/',
                '"encrypted":"[REDACTED]"',
                $message
            );
        }

        return $message;
    }
}
