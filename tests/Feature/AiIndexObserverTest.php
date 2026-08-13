<?php

namespace Tests\Feature;

use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\ResearchAdviser;
use App\Models\RuleRegulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiIndexObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    /**
     * ShouldBeUnique jobs acquire a cache lock at dispatch; a second dispatch
     * of the same uniqueId within uniqueFor is skipped (the debounce). Release
     * the lock so a test can observe a subsequent dispatch.
     */
    private function releaseUniqueLock(string $jobClass, string $uniqueId): void
    {
        Cache::lock('laravel_unique_job:'.$jobClass.':'.$uniqueId)->forceRelease();
    }

    #[Test]
    public function creating_a_paper_dispatches_a_debounced_rebuild(): void
    {
        AcademicPaper::factory()->create();

        Bus::assertDispatched(AiIndexRebuildJob::class, function ($job) {
            // Illuminate Carbon's diffInSeconds is signed; the delay is 60s in
            // the future regardless of sign convention.
            return $job->delay !== null
                && abs($job->delay->diffInSeconds(now())) >= 58
                && abs($job->delay->diffInSeconds(now())) <= 62;
        });
    }

    #[Test]
    public function updating_a_paper_dispatches_a_debounced_rebuild(): void
    {
        // Fresh DB: no authors exist, so factory create fires created(1) only.
        $paper = AcademicPaper::factory()->create();
        Bus::assertDispatched(AiIndexRebuildJob::class, 1);

        // Release the unique-job lock (debounce) so the update's dispatch lands.
        $this->releaseUniqueLock(AiIndexRebuildJob::class, 'ai-index-rebuild');

        $paper->update(['title' => 'Updated title']);

        Bus::assertDispatched(AiIndexRebuildJob::class, 2);
    }

    #[Test]
    public function deleting_a_paper_dispatches_an_immediate_rebuild(): void
    {
        $paper = AcademicPaper::factory()->create();

        $paper->delete();

        Bus::assertDispatched(AiIndexRebuildImmediateJob::class);
    }

    #[Test]
    public function creating_a_people_name_dispatches_a_debounced_rebuild(): void
    {
        ResearchAdviser::factory()->create();

        Bus::assertDispatched(AiIndexRebuildJob::class);
    }

    #[Test]
    public function deleting_a_regulation_dispatches_an_immediate_rebuild(): void
    {
        $regulation = RuleRegulation::factory()->create();

        $regulation->delete();

        Bus::assertDispatched(AiIndexRebuildImmediateJob::class);
    }

    #[Test]
    public function attaching_an_author_touches_the_paper_and_dispatches_a_rebuild(): void
    {
        // Fresh DB: factory create fires created(1) only.
        $paper = AcademicPaper::factory()->create();
        Bus::assertDispatched(AiIndexRebuildJob::class, 1);

        // author create suppressed via withoutEvents (PeopleNameObserver)
        $author = Author::withoutEvents(fn () => Author::factory()->create());

        // Release the unique-job lock (debounce) so the attach's dispatch lands.
        $this->releaseUniqueLock(AiIndexRebuildJob::class, 'ai-index-rebuild');

        $paper->authors()->attach($author);

        // Pivot created event fires the debounced job.
        Bus::assertDispatched(AiIndexRebuildJob::class, 2);
    }
}
