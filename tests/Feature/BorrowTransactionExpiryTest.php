<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\ScoreIncrement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowTransactionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrow_transaction_expires_after_3_hours()
    {
        // Create test data
        $user = User::factory()->create(['role_id' => 1]); // Student role
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        // Create borrow transaction
        $timeIn = now();
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => $timeIn,
            'expires_at' => $timeIn->copy()->addHours(3),
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Assert expires_at is exactly 3 hours from time_in
        $this->assertEquals(3, $transaction->time_in->diffInHours($transaction->expires_at));
        $this->assertEquals(180, $transaction->time_in->diffInMinutes($transaction->expires_at));
    }

    public function test_on_time_return_awards_credit_score_and_notification()
    {
        $user = User::factory()->create(['role_id' => 1, 'credit_score' => 100]);
        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        $timeIn = now()->subHours(2); // 2 hours ago
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => $timeIn,
            'expires_at' => $timeIn->copy()->addHours(3), // Expires in 1 hour from now
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Return on time (before 3 hours)
        $transaction->update([
            'time_out' => now(),
            'status' => 'completed',
        ]);

        // Assert credit score was awarded
        $scoreIncrement = ScoreIncrement::where('user_id', $user->id)
            ->where('related_borrow_transaction_id', $transaction->id)
            ->first();

        $this->assertNotNull($scoreIncrement, 'Score increment should be created for on-time return');
        $this->assertEquals(10, $scoreIncrement->score_value);

        // Assert notification was created
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'paper_returned')
            ->first();

        $this->assertNotNull($notification, 'On-time return notification should be created');
        $this->assertStringContainsString('on time', $notification->message);
        $this->assertStringContainsString('+10 credit score', $notification->message);
    }

    public function test_late_return_no_credit_score_but_notification()
    {
        $user = User::factory()->create(['role_id' => 1, 'credit_score' => 100]);
        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        $timeIn = now()->subHours(4); // 4 hours ago
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => $timeIn,
            'expires_at' => $timeIn->copy()->addHours(3), // Expired 1 hour ago
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Return late (after 3 hours)
        $transaction->update([
            'time_out' => now(),
            'status' => 'completed',
        ]);

        // Assert NO credit score was awarded
        $scoreIncrement = ScoreIncrement::where('user_id', $user->id)
            ->where('related_borrow_transaction_id', $transaction->id)
            ->first();

        $this->assertNull($scoreIncrement, 'No score increment for late return');

        // Assert late notification was created
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'paper_returned_late')
            ->first();

        $this->assertNotNull($notification, 'Late return notification should be created');
        $this->assertStringContainsString('late', $notification->message);
        $this->assertStringContainsString('No credit score awarded', $notification->message);
    }

    public function test_overdue_transaction_triggers_notification()
    {
        $user = User::factory()->create(['role_id' => 1]);
        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        $timeIn = now()->subHours(4);
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => $timeIn,
            'expires_at' => $timeIn->copy()->addHours(3),
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Mark as overdue
        $transaction->update(['status' => 'overdue']);

        // Assert overdue notification was created
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'paper_overdue')
            ->first();

        $this->assertNotNull($notification, 'Overdue notification should be created');
        $this->assertStringContainsString('overdue', $notification->message);
        $this->assertNotNull($transaction->fresh()->overdue_notified_at);
    }
}
