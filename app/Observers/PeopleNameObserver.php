<?php

namespace App\Observers;

use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use Illuminate\Database\Eloquent\Model;

class PeopleNameObserver
{
    public function created(Model $model): void
    {
        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function updated(Model $model): void
    {
        AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
    }

    public function deleted(Model $model): void
    {
        AiIndexRebuildImmediateJob::dispatch();
    }
}
