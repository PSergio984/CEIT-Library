<?php

namespace App\Support;

/**
 * Canonical vocabulary shared by the corpus export and the sidecar
 * filters sent to it: a value must look the same in catalog.json and
 * in a filters payload or the sidecar's exact/substring matching breaks.
 */
class CorpusNormalizer
{
    public static function text(?string $value, int $maxLen): string
    {
        $value = $value ?? '';

        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }

        return $value;
    }

    public static function personName(?string $value): string
    {
        $name = self::text($value, 200);

        if (mb_strtolower($name) === 'asdasd') {
            return '';
        }

        // Drop an injected leading honorific only when a second one
        // follows — 'Engr. Mr. X' -> 'Mr. X', 'Engr. Jose Rizal' stays.
        return preg_replace(
            '/^(?:Dr|Engr)\.\s+(?=(?:Dr|Engr|Mr|Ms|Mrs|Miss|Prof|Atty|Arch)\.\s)/i',
            '',
            $name
        ) ?? $name;
    }

    public static function paperType(?string $value): string
    {
        $type = self::text($value, 200);

        return mb_strtolower($type) === 'feasib' ? 'Feasibility' : $type;
    }
}
