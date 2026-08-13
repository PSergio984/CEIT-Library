<?php

namespace App\Observers;

use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;

class AcademicPaperObserver
{
    public function created(AcademicPaper $paper): void
    {
        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function updated(AcademicPaper $paper): void
    {
        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function deleted(AcademicPaper $paper): void
    {
        AiIndexRebuildImmediateJob::dispatch();
    }
}
