<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebugEvents8Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function debug(): void
    {
        $dispatcher = AcademicPaper::getEventDispatcher();

        $paper = AcademicPaper::factory()->create(['catalog_code' => 'CEIT-DB-08-01']);

        $observable = (new AcademicPaper)->getObservableEvents();
        fwrite(STDERR, 'OBSERVABLE: '.json_encode($observable).PHP_EOL);

        try {
            $dispatcher->dispatch('eloquent.updated: '.AcademicPaper::class, [$paper]);
            fwrite(STDERR, 'MANUAL UPDATED DISPATCH: OK'.PHP_EOL);
        } catch (\Throwable $e) {
            fwrite(STDERR, 'MANUAL UPDATED ERROR: '.get_class($e).': '.$e->getMessage().PHP_EOL);
        }

        $this->assertTrue(true);
    }
}
