<?php

namespace App\Observers;

use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use App\Support\AiIndexSync;
use Illuminate\Database\Eloquent\Model;

class RulebookObserver
{
    public function created(Model $model): void
    {
        if (AiIndexSync::suppressed()) {
            return;
        }

        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function updated(Model $model): void
    {
        if (AiIndexSync::suppressed()) {
            return;
        }

        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function deleted(Model $model): void
    {
        if (AiIndexSync::suppressed()) {
            return;
        }

        AiIndexRebuildImmediateJob::dispatch();
    }
}
