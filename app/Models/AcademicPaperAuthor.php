<?php

namespace App\Models;

use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AcademicPaperAuthor extends Pivot
{
    /**
     * Author attach/detach changes the indexed people fields — queue a
     * rebuild. Pivot model events DO fire on attach/detach; the parent's
     * updated event does not (Model::touch uses saveQuietly).
     */
    protected static function booted(): void
    {
        static::created(function () {
            AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
        });

        static::deleted(function () {
            AiIndexRebuildImmediateJob::dispatch();
        });
    }

    public function academicPaper(): BelongsTo
    {
        return $this->belongsTo(AcademicPaper::class);
    }
}
