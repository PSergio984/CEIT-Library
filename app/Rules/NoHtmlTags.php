<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value does not contain HTML tags or script injections.
 */
class NoHtmlTags implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string $message, ?string $attribute = null): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $normalized = $this->normalizeInput($value);

        if ($this->containsHtmlTags($normalized)) {
            $fail('The :attribute cannot contain HTML tags.', $attribute);

            return;
        }

        if ($this->containsUnsafeProtocols($normalized)) {
            $fail('The :attribute contains potentially unsafe content.', $attribute);

            return;
        }

        if ($this->containsEventHandlers($normalized)) {
            $fail('The :attribute contains potentially unsafe content.', $attribute);

            return;
        }
    }

    /**
     * Normalize input by URL-decoding, HTML-entity-decoding, and stripping dangerous characters.
     */
    private function normalizeInput(string $value): string
    {
        $normalized = $value;

        $normalized = urldecode($normalized);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = str_replace("\0", '', $normalized);
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalized);

        return $normalized;
    }

    /**
     * Check if the input contains HTML tags using strip_tags as a comprehensive check.
     */
    private function containsHtmlTags(string $value): bool
    {
        $stripped = strip_tags($value);

        return $stripped !== $value;
    }

    /**
     * Check for unsafe protocols including javascript:, vbscript:, mhtml:, ms-its:, and others.
     * Handles data: URIs specially - only blocks HTML-bearing data URIs.
     */
    private function containsUnsafeProtocols(string $value): bool
    {
        $unsafeProtocols = [
            'javascript:',
            'vbscript:',
            'mhtml:',
            'ms-its:',
            'file:',
            'data:text/html',
            'data:text/plain',
        ];

        $lowerValue = strtolower($value);

        foreach ($unsafeProtocols as $protocol) {
            if (str_contains($lowerValue, $protocol)) {
                return true;
            }
        }

        if (preg_match('/data:\s*[^;]*\/html/i', $value)) {
            return true;
        }

        if (preg_match('/data:\s*[^;]*\/plain/i', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Check for event handler attributes (onclick, onload, etc.).
     */
    private function containsEventHandlers(string $value): bool
    {
        return (bool) preg_match('/on\w+\s*=/i', $value);
    }
}
