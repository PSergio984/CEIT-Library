<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Librarian;
use App\Models\Notification;
use App\Models\ScoreIncrement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the credit score incentive system with specific limits:
 * - Borrow: +10 credit for on-time returns if borrowed >= 30 minutes
 * - Attendance: +5 credit for staying >= 30 minutes
 * - Daily limit: Max 3 credit score rewards per day for EACH type
 */
class CreditScoreIncentiveLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Librarian $librarian;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with initial credit score
        $this->user = User::factory()->create(['credit_score' => 50]);

        // Create librarian for attendance records
        $this->librarian = Librarian::factory()->create();
    }

    // ========================================
    // BORROW TRANSACTION TESTS
    // ========================================

    /**
     * Test: On-time return with 30+ minute borrow earns +10 credit score
     */
    public function test_borrow_credit_awarded_for_on_time_return_with_30_plus_minutes(): void
    {
        $timeIn = now()->subMinutes(45);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now(); // Before expires_at, so on time

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        // Complete the transaction
        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        // Assert credit score awarded
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'score_value' => 10,
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(60, $this->user->credit_score); // 50 + 10
    }

    /**
     * Test: On-time return with less than 30 minutes borrow does NOT earn credit score
     */
    public function test_borrow_credit_not_awarded_for_duration_less_than_30_minutes(): void
    {
        $timeIn = now()->subMinutes(20); // Only 20 minutes
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        // Complete the transaction
        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        // Assert NO credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(50, $this->user->credit_score); // No change

        // Assert notification mentions the duration requirement
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'paper_returned',
        ]);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'paper_returned')
            ->first();

        $this->assertStringContains('minimum 30 min required', $notification->message);
    }

    /**
     * Test: Late return does NOT earn credit score regardless of duration
     */
    public function test_borrow_credit_not_awarded_for_late_return(): void
    {
        $timeIn = now()->subHours(8); // 8 hours ago
        $expiresAt = $timeIn->copy()->addHours(6); // Expired 2 hours ago
        $timeOut = now(); // Late return

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'overdue');

        // Complete the transaction (late)
        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        // Assert NO credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(50, $this->user->credit_score); // No change

        // Assert late return notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'paper_returned_late',
        ]);
    }

    /**
     * Test: Daily limit of 3 credit score rewards for borrowing
     * The 4th on-time return on the same day should NOT earn credit
     */
    public function test_borrow_credit_not_awarded_after_daily_limit_of_3(): void
    {
        // Create 3 existing On-Time Return rewards for today
        for ($i = 1; $i <= 3; $i++) {
            ScoreIncrement::create([
                'user_id' => $this->user->id,
                'name' => 'On-Time Return',
                'description' => "Previous return #{$i}",
                'score_value' => 10,
            ]);
        }

        $this->user->refresh();
        $this->assertEquals(80, $this->user->credit_score); // 50 + 30

        // Now try the 4th return
        $timeIn = now()->subMinutes(45);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        // Complete the transaction
        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        // Assert NO additional credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(80, $this->user->credit_score); // Still 80, no additional credit

        // Assert daily limit notification
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'paper_returned')
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContains('Daily credit limit reached', $notification->message);
    }

    /**
     * Test: Daily limit resets the next day
     */
    public function test_borrow_credit_limit_resets_next_day(): void
    {
        // Create 3 On-Time Return rewards for YESTERDAY
        Carbon::setTestNow(Carbon::yesterday());

        for ($i = 1; $i <= 3; $i++) {
            ScoreIncrement::create([
                'user_id' => $this->user->id,
                'name' => 'On-Time Return',
                'description' => "Yesterday return #{$i}",
                'score_value' => 10,
            ]);
        }

        $this->user->refresh();
        $this->assertEquals(80, $this->user->credit_score); // 50 + 30

        // Reset to today
        Carbon::setTestNow();

        // Now do a return TODAY - should be allowed (1st of today)
        $timeIn = now()->subMinutes(45);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        // Assert credit score WAS awarded (daily limit reset)
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(90, $this->user->credit_score); // 80 + 10
    }

    // ========================================
    // ATTENDANCE TESTS
    // ========================================

    /**
     * Test: Attendance 30+ minutes earns +5 credit score
     */
    public function test_attendance_credit_awarded_for_30_plus_minutes(): void
    {
        $timeIn = now()->subMinutes(45);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'role_id' => $this->user->role_id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => 'active',
            'scanned_by' => $this->librarian->id,
            'duration_minutes' => null,
        ]);

        // Complete the attendance
        $attendance->update([
            'time_out' => now(),
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);

        // Assert credit score awarded
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'Attendance 30+ Minutes',
            'score_value' => 5,
            'related_attendance_id' => $attendance->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(55, $this->user->credit_score); // 50 + 5
    }

    /**
     * Test: Attendance less than 30 minutes does NOT earn credit score
     */
    public function test_attendance_credit_not_awarded_for_less_than_30_minutes(): void
    {
        $timeIn = now()->subMinutes(20);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'role_id' => $this->user->role_id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => 'active',
            'scanned_by' => $this->librarian->id,
            'duration_minutes' => null,
        ]);

        // Complete the attendance with less than 30 minutes
        $attendance->update([
            'time_out' => now(),
            'status' => 'completed',
            'duration_minutes' => 20,
        ]);

        // Assert NO credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'Attendance 30+ Minutes',
            'related_attendance_id' => $attendance->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(50, $this->user->credit_score); // No change
    }

    /**
     * Test: Daily limit of 3 credit score rewards for attendance
     * The 4th 30+ minute attendance on the same day should NOT earn credit
     */
    public function test_attendance_credit_not_awarded_after_daily_limit_of_3(): void
    {
        // Create 3 existing Attendance 30+ Minutes rewards for today
        for ($i = 1; $i <= 3; $i++) {
            ScoreIncrement::create([
                'user_id' => $this->user->id,
                'name' => 'Attendance 30+ Minutes',
                'description' => "Previous attendance #{$i}",
                'score_value' => 5,
            ]);
        }

        $this->user->refresh();
        $this->assertEquals(65, $this->user->credit_score); // 50 + 15

        // Now try the 4th attendance
        $timeIn = now()->subMinutes(45);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'role_id' => $this->user->role_id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => 'active',
            'scanned_by' => $this->librarian->id,
            'duration_minutes' => null,
        ]);

        $attendance->update([
            'time_out' => now(),
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);

        // Assert NO additional credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'related_attendance_id' => $attendance->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(65, $this->user->credit_score); // Still 65, no additional credit

        // Assert daily limit notification
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'attendance_checkout')
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContains('Daily credit limit reached', $notification->message);
    }

    /**
     * Test: Attendance daily limit resets the next day
     */
    public function test_attendance_credit_limit_resets_next_day(): void
    {
        // Create 3 attendance rewards for YESTERDAY
        Carbon::setTestNow(Carbon::yesterday());

        for ($i = 1; $i <= 3; $i++) {
            ScoreIncrement::create([
                'user_id' => $this->user->id,
                'name' => 'Attendance 30+ Minutes',
                'description' => "Yesterday attendance #{$i}",
                'score_value' => 5,
            ]);
        }

        $this->user->refresh();
        $this->assertEquals(65, $this->user->credit_score); // 50 + 15

        // Reset to today
        Carbon::setTestNow();

        // Now do an attendance TODAY - should be allowed (1st of today)
        $timeIn = now()->subMinutes(45);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'role_id' => $this->user->role_id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => 'active',
            'scanned_by' => $this->librarian->id,
            'duration_minutes' => null,
        ]);

        $attendance->update([
            'time_out' => now(),
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);

        // Assert credit score WAS awarded (daily limit reset)
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'Attendance 30+ Minutes',
            'related_attendance_id' => $attendance->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(70, $this->user->credit_score); // 65 + 5
    }

    // ========================================
    // COMBINED TESTS
    // ========================================

    /**
     * Test: Borrow and Attendance limits are INDEPENDENT
     * User can earn 3 borrow credits + 3 attendance credits on same day
     */
    public function test_borrow_and_attendance_limits_are_independent(): void
    {
        // Create 3 borrow rewards for today
        for ($i = 1; $i <= 3; $i++) {
            ScoreIncrement::create([
                'user_id' => $this->user->id,
                'name' => 'On-Time Return',
                'description' => "Return #{$i}",
                'score_value' => 10,
            ]);
        }

        $this->user->refresh();
        $this->assertEquals(80, $this->user->credit_score); // 50 + 30

        // User should STILL be able to earn attendance credits
        $timeIn = now()->subMinutes(45);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'role_id' => $this->user->role_id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => 'active',
            'scanned_by' => $this->librarian->id,
            'duration_minutes' => null,
        ]);

        $attendance->update([
            'time_out' => now(),
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);

        // Assert attendance credit WAS awarded (independent limit)
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'Attendance 30+ Minutes',
            'related_attendance_id' => $attendance->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(85, $this->user->credit_score); // 80 + 5
    }

    /**
     * Test: User can earn maximum of 45 credit points per day (3x10 + 3x5)
     */
    public function test_maximum_daily_credit_from_incentives(): void
    {
        // Earn all 3 borrow credits
        for ($i = 1; $i <= 3; $i++) {
            $timeIn = now()->subMinutes(45 + $i * 10);
            $expiresAt = $timeIn->copy()->addHours(6);
            $timeOut = now()->subMinutes($i * 10);

            $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

            $transaction->update([
                'time_out' => $timeOut,
                'status' => 'completed',
                'duration_minutes' => $timeIn->diffInMinutes($timeOut),
            ]);
        }

        // Earn all 3 attendance credits
        for ($i = 1; $i <= 3; $i++) {
            $attendance = Attendance::create([
                'user_id' => $this->user->id,
                'role_id' => $this->user->role_id,
                'time_in' => now()->subHours($i + 3),
                'time_out' => null,
                'status' => 'active',
                'scanned_by' => $this->librarian->id,
                'duration_minutes' => null,
            ]);

            $attendance->update([
                'time_out' => now()->subHours($i + 2),
                'status' => 'completed',
                'duration_minutes' => 60,
            ]);
        }

        $this->user->refresh();
        // 50 (base) + 30 (3x10 borrow) + 15 (3x5 attendance) = 95
        $this->assertEquals(95, $this->user->credit_score);

        // Verify counts
        $borrowRewards = ScoreIncrement::where('user_id', $this->user->id)
            ->where('name', 'On-Time Return')
            ->whereDate('created_at', today())
            ->count();

        $attendanceRewards = ScoreIncrement::where('user_id', $this->user->id)
            ->where('name', 'Attendance 30+ Minutes')
            ->whereDate('created_at', today())
            ->count();

        $this->assertEquals(3, $borrowRewards);
        $this->assertEquals(3, $attendanceRewards);
    }

    /**
     * Test: Credit score capped at 100 even with multiple rewards
     */
    public function test_credit_score_capped_at_100(): void
    {
        // Set user to 95 credit
        $this->user->update(['credit_score' => 95]);

        // Earn a borrow credit (+10 would exceed 100)
        $timeIn = now()->subMinutes(45);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => $timeIn->diffInMinutes($timeOut),
        ]);

        $this->user->refresh();
        $this->assertEquals(100, $this->user->credit_score); // Capped at 100, not 105
    }

    // ========================================
    // EDGE CASE TESTS
    // ========================================

    /**
     * Test: Exactly 30 minute borrow duration qualifies for credit
     */
    public function test_borrow_exactly_30_minutes_qualifies_for_credit(): void
    {
        $timeIn = now()->subMinutes(30);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => 30,
        ]);

        // Assert credit score was awarded (30 minutes is the minimum)
        $this->assertDatabaseHas('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(60, $this->user->credit_score); // 50 + 10
    }

    /**
     * Test: 29 minute borrow duration does NOT qualify for credit
     */
    public function test_borrow_29_minutes_does_not_qualify_for_credit(): void
    {
        $timeIn = now()->subMinutes(29);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => 29,
        ]);

        // Assert NO credit score was awarded
        $this->assertDatabaseMissing('score_increments', [
            'user_id' => $this->user->id,
            'name' => 'On-Time Return',
            'related_borrow_transaction_id' => $transaction->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(50, $this->user->credit_score); // No change
    }

    /**
     * Test: Idempotency - completing same transaction twice doesn't award double credit
     */
    public function test_borrow_credit_idempotent_no_double_award(): void
    {
        $timeIn = now()->subMinutes(45);
        $expiresAt = $timeIn->copy()->addHours(6);
        $timeOut = now();

        $transaction = $this->createBorrowTransaction($timeIn, $expiresAt, 'started');

        // Complete the transaction first time
        $transaction->update([
            'time_out' => $timeOut,
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);

        $this->user->refresh();
        $this->assertEquals(60, $this->user->credit_score); // 50 + 10

        // Try to "complete" again (simulate double update)
        $transaction->status = 'started';
        $transaction->save(['timestamps' => false]);

        $transaction->update([
            'status' => 'completed',
        ]);

        $this->user->refresh();
        $this->assertEquals(60, $this->user->credit_score); // Still 60, no double award

        // Verify only 1 score increment exists for this transaction
        $count = ScoreIncrement::where('related_borrow_transaction_id', $transaction->id)->count();
        $this->assertEquals(1, $count);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Helper to create a borrow transaction with required relationships
     */
    protected function createBorrowTransaction(Carbon $timeIn, Carbon $expiresAt, string $status): BorrowTransaction
    {
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        return BorrowTransaction::create([
            'user_id' => $this->user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => $timeIn,
            'time_out' => null,
            'status' => $status,
            'expires_at' => $expiresAt,
            'session_token' => \Illuminate\Support\Str::random(64),
        ]);
    }

    /**
     * Custom assertion to check if string contains substring
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
