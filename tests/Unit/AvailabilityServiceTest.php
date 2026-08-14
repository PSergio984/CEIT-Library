<?php

namespace Tests\Unit;

use App\Models\AcademicPaper;
use App\Models\Dean;
use App\Models\Inventory;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    private function seedAdvisers(): void
    {
        ResearchAdviser::factory()->create(['name' => 'Engr. Jose Rizal']);
        TechnicalAdviser::factory()->create(['name' => 'Engr. Andres Bonifacio']);
        Dean::factory()->create(['name' => 'Dr. Emilio Aguinaldo']);
    }

    private function paperWithCopies(array $statuses): AcademicPaper
    {
        $paper = AcademicPaper::factory()->create();

        foreach ($statuses as $index => $status) {
            Inventory::factory()->create([
                'academic_paper_id' => $paper->id,
                'copy_number' => $index + 1,
                'status' => $status,
            ]);
        }

        return $paper;
    }

    public function test_hydrates_mixed_copy_statuses()
    {
        $this->seedAdvisers();

        $paperA = $this->paperWithCopies(['Available', 'Available', 'Reserved', 'Unavailable']);
        $paperB = $this->paperWithCopies(['Unavailable']);
        $paperC = $this->paperWithCopies(['Available', 'Available', 'Available']);

        $result = (new AvailabilityService)->forPapers([$paperA->id, $paperB->id, $paperC->id]);

        $this->assertSame(['available' => 2, 'total' => 4], $this->counts($result[$paperA->id]));
        $this->assertSame(['available' => 0, 'total' => 1], $this->counts($result[$paperB->id]));
        $this->assertSame(['available' => 3, 'total' => 3], $this->counts($result[$paperC->id]));
    }

    public function test_checked_at_is_now()
    {
        $this->seedAdvisers();

        $paper = $this->paperWithCopies(['Available', 'Unavailable']);

        $result = (new AvailabilityService)->forPapers([$paper->id]);

        $this->assertLessThan(5, now()->diffInSeconds($result[$paper->id]['checked_at']));
    }

    public function test_omits_papers_without_inventory_rows()
    {
        $this->seedAdvisers();

        $paper = AcademicPaper::factory()->create();

        $result = (new AvailabilityService)->forPapers([$paper->id]);

        $this->assertArrayNotHasKey($paper->id, $result);
        $this->assertSame([], $result);
    }

    public function test_empty_ids_returns_empty_array_without_query()
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $result = (new AvailabilityService)->forPapers([]);

        $this->assertSame([], $result);
        $this->assertSame(0, $queries);
    }

    public function test_never_persists_checked_at()
    {
        $this->seedAdvisers();

        $paper = $this->paperWithCopies(['Available', 'Unavailable']);
        $copies = $paper->copies()->orderBy('id')->get();
        $before = $copies->map(fn ($copy) => $copy->getAttributes())->all();

        (new AvailabilityService)->forPapers([$paper->id]);

        $after = $paper->copies()->orderBy('id')->get()
            ->map(fn ($copy) => $copy->getAttributes())->all();

        $this->assertSame($before, $after);
        $this->assertFalse(Schema::hasColumn('inventories', 'checked_at'));
    }

    private function counts(array $entry): array
    {
        return [
            'available' => $entry['available'],
            'total' => $entry['total'],
        ];
    }
}
