<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that text is safe (no control characters, null bytes, or suspicious patterns).
 */
class SafeText implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Let 'required' rule handle empty values
        }

        // Check for null bytes
        if (str_contains($value, "\0")) {
            $fail('The :attribute contains invalid characters.');

            return;
        }

        // Check for control characters (except newline, carriage return, tab)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            $fail('The :attribute contains invalid control characters.');

            return;
        }

        // Check for excessive whitespace (more than 3 consecutive newlines or 10 spaces)
        if (preg_match('/\n{4,}|\s{10,}/', $value)) {
            $fail('The :attribute contains excessive whitespace.');

            return;
        }
    }
}
