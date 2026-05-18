<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a value is a proper name (no numbers, only letters, spaces, hyphens, apostrophes, periods).
 * Supports Unicode characters for international names.
 */
class ProperName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Let 'required' rule handle empty values
        }

        // Must contain at least one letter
        if (! preg_match('/[\p{L}]/u', $value)) {
            $fail('The :attribute must contain at least one letter.');

            return;
        }

        // Only allow letters (Unicode), spaces, hyphens, apostrophes, periods, and commas
        // Supports names like: María, O'Connor, Jean-Pierre, Dr. Smith, Santos Jr.
        if (! preg_match('/^[\p{L}\s\-\'\.,()\x{2018}\x{2019}\x{201C}\x{201D}]+$/u', $value)) {
            $fail('The :attribute may only contain letters, spaces, hyphens, apostrophes, and periods.');

            return;
        }

        // Must not start or end with special characters (except period for abbreviations)
        if (preg_match('/^[\-\'\s,()]|[\-\'\s,()]$/u', $value)) {
            $fail('The :attribute cannot start or end with special characters.');

            return;
        }

        // Must not have repeated hyphens, apostrophes, or other duplicated special chars (except spaces in "Dr. Smith" etc.)
        if (preg_match('/[\-\']{2,}|[.,]{2,}|[()]{2,}/u', $value)) {
            $fail('The :attribute cannot contain repeated special characters.');

            return;
        }

        // Must not be all special characters/spaces
        $lettersOnly = preg_replace('/[^\p{L}]/u', '', $value);
        if (mb_strlen($lettersOnly) < 2) {
            $fail('The :attribute must contain at least two letters.');
        }
    }
}
