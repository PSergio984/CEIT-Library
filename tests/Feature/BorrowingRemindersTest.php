<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingRemindersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_it_sends_reminders_for_transactions_due_in_three_days(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create(['academic_paper_id' => $paper->id]);

        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'expires_at' => Carbon::now()->addDays(3)->startOfDay()->addHours(12), // Middle of the day in 3 days
            'reminder_notified_at' => null,
        ]);

        $this->artisan('library:send-reminders')
            ->expectsOutput('Checking for due-soon transactions...')
            ->expectsOutput('Sent 1 due-soon reminders.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'reminders',
            'title' => 'Return Reminder',
        ]);

        $this->assertNotNull($transaction->fresh()->reminder_notified_at);
    }

    /** @test */
    public function test_it_sends_overdue_follow_up_reminders(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create(['academic_paper_id' => $paper->id]);

        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'overdue',
            'expires_at' => Carbon::now()->subDays(2),
            'overdue_notified_at' => Carbon::now()->subDays(1), // Notified yesterday
        ]);

        $this->artisan('library:send-reminders')
            ->expectsOutput('Checking for daily overdue follow-ups...')
            ->expectsOutput('Sent 1 overdue follow-up reminders.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'reminders',
            'title' => 'OVERDUE NOTICE',
        ]);

        $transaction->refresh();
        $this->assertNotNull($transaction->overdue_notified_at);
        $this->assertTrue($transaction->overdue_notified_at->greaterThan(Carbon::now()->subDays(1)));
    }
}
