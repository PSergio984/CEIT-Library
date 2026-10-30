<?php

namespace App\Support;

/**
 * Suppresses AI index rebuild dispatches during bulk writes (catalog
 * import) so a batch produces one rebuild after commit instead of one
 * per model event. Checked by the index-sync observers and the
 * AcademicPaperAuthor pivot hook — AcademicPaper's catalog-code
 * `creating` hook is untouched and keeps running.
 */
class AiIndexSync
{
    private static bool $suppressed = false;

    public static function suppressed(): bool
    {
        return self::$suppressed;
    }

    /**
     * Run the callback with index rebuild dispatches suppressed. The
     * previous flag is restored afterwards so nested calls stay safe.
     */
    public static function withoutDispatches(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }
}
