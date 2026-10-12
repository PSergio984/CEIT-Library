<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportBorrowSequencesTest extends TestCase
{
    use RefreshDatabase;

    private string $exportPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportPath = storage_path('app/testing/borrow-sequences.json');

        File::delete($this->exportPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->exportPath);
        File::deleteDirectory(storage_path('app/testing'));

        parent::tearDown();
    }

    private function borrow(User $user, AcademicPaper $paper, string $status, Carbon $timeIn): BorrowTransaction
    {
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        return BorrowTransaction::factory()->create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => $status,
            'time_in' => $timeIn,
            'time_out' => $status === 'completed' ? $timeIn->copy()->addHour() : null,
            'expires_at' => $timeIn->copy()->addHours(6),
        ]);
    }

    private function readExport(string $path): array
    {
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true);
    }

    #[Test]
    public function it_exports_sequences_keyed_by_sequential_index_ordered_oldest_first_across_all_statuses(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $paper1 = AcademicPaper::factory()->create();
        $paper2 = AcademicPaper::factory()->create();
        $paper3 = AcademicPaper::factory()->create();
        $paper4 = AcademicPaper::factory()->create();

        // Created out of order on purpose: ordering must follow time_in, not row order
        $this->borrow($userA, $paper1, 'started', Carbon::now()->subDays(1));
        $this->borrow($userA, $paper2, 'completed', Carbon::now()->subDays(3));
        $this->borrow($userA, $paper3, 'overdue', Carbon::now()->subDays(2));
        $this->borrow($userB, $paper4, 'completed', Carbon::now()->subDays(5));

        $this->artisan('ai:export-borrow-sequences', ['--output' => $this->exportPath])
            ->assertExitCode(0);

        $data = $this->readExport($this->exportPath);

        $this->assertSame(2, $data['user_count']);
        $this->assertSame([0, 1], array_keys($data['sequences']));

        // Every status counts; oldest borrow first
        $this->assertSame(
            [$paper2->id, $paper3->id, $paper1->id],
            $data['sequences']['0']
        );
        $this->assertSame([$paper4->id], $data['sequences']['1']);

        // Paper ids are exported as plain integers
        foreach ($data['sequences'] as $sequence) {
            foreach ($sequence as $paperId) {
                $this->assertIsInt($paperId);
            }
        }
    }

    #[Test]
    public function it_contains_no_user_identifiers_anywhere_in_the_output(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Zxqyidentifiable',
            'last_name' => 'Qvwxname',
            'email' => 'zxqy-identifiable@example.com',
        ]);

        $paper = AcademicPaper::factory()->create();

        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        $this->artisan('ai:export-borrow-sequences', ['--output' => $this->exportPath])
            ->assertExitCode(0);

        $encoded = file_get_contents($this->exportPath);
        $data = json_decode($encoded, true);

        $this->assertStringNotContainsString($user->first_name, $encoded);
        $this->assertStringNotContainsString($user->last_name, $encoded);
        $this->assertStringNotContainsString($user->email, $encoded);
        $this->assertStringNotContainsString('user_id', $encoded);
        $this->assertStringNotContainsString('email', $encoded);
        $this->assertStringNotContainsString('name', $encoded);
        $this->assertStringNotContainsString('session_token', $encoded);
        $this->assertStringNotContainsString('inventory_id', $encoded);
        $this->assertStringNotContainsString('transaction', $encoded);

        // The anonymous index must not leak the real user id
        $this->assertArrayNotHasKey((string) $user->id, $data['sequences']);
    }

    #[Test]
    public function it_writes_to_the_default_path_under_storage_app_when_no_output_is_given(): void
    {
        $corpusPath = storage_path('app/testing/ai-corpus');
        config()->set('services.ai_sidecar.corpus_path', $corpusPath);

        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();

        $this->borrow($user, $paper, 'started', Carbon::now()->subHour());

        $this->artisan('ai:export-borrow-sequences')->assertExitCode(0);

        $data = $this->readExport($corpusPath.'/borrow-sequences.json');

        $this->assertSame([$paper->id], $data['sequences']['0']);

        File::deleteDirectory(storage_path('app/testing'));
    }

    #[Test]
    public function it_exports_an_empty_sequences_object_when_there_are_no_borrows(): void
    {
        $this->artisan('ai:export-borrow-sequences', ['--output' => $this->exportPath])
            ->assertExitCode(0);

        $data = $this->readExport($this->exportPath);

        $this->assertSame(0, $data['user_count']);
        $this->assertSame([], $data['sequences']);
    }
}
