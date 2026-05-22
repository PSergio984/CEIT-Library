<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendDeadlineWarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_warnings_to_active_transactions_expiring_within_30_minutes(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        // Transaction expiring in 15 minutes (within 30 minutes window)
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => now()->subHours(2)->subMinutes(45),
            'expires_at' => now()->addMinutes(15),
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Run the command
        $this->artisan('transactions:send-deadline-warnings')
            ->expectsOutput('Sent 1 deadline warnings successfully.')
            ->assertExitCode(0);

        // Assert notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'paper_deadline_warning',
            'title' => 'Deadline Approaching!',
        ]);

        // Assert warning_notified_at was updated
        $this->assertNotNull($transaction->fresh()->warning_notified_at);
    }

    public function test_it_does_not_send_duplicate_warnings(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        // Transaction expiring in 15 minutes, but already warned
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => now()->subHours(2)->subMinutes(45),
            'expires_at' => now()->addMinutes(15),
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
            'warning_notified_at' => now()->subMinutes(5),
        ]);

        // Run the command
        $this->artisan('transactions:send-deadline-warnings')
            ->expectsOutput('No borrow transactions require deadline warnings.')
            ->assertExitCode(0);

        // Assert no new notifications were created
        $this->assertEquals(0, Notification::count());
    }

    public function test_it_does_not_send_warnings_for_transactions_expiring_after_30_minutes(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        // Transaction expiring in 45 minutes (outside 30 minutes window)
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => now()->subHours(2)->subMinutes(15),
            'expires_at' => now()->addMinutes(45),
            'status' => 'started',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Run the command
        $this->artisan('transactions:send-deadline-warnings')
            ->expectsOutput('No borrow transactions require deadline warnings.')
            ->assertExitCode(0);

        // Assert no notification was created
        $this->assertEquals(0, Notification::count());
        $this->assertNull($transaction->fresh()->warning_notified_at);
    }

    public function test_it_does_not_send_warnings_for_completed_or_overdue_transactions(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        // Transaction completed but expires_at is in 15 minutes
        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => now()->subHours(2),
            'time_out' => now(),
            'expires_at' => now()->addMinutes(15),
            'status' => 'completed',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Transaction overdue (expires_at in the past)
        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => now()->subHours(4),
            'expires_at' => now()->subMinutes(15),
            'status' => 'overdue',
            'session_token' => bin2hex(random_bytes(32)),
        ]);

        // Run the command
        $this->artisan('transactions:send-deadline-warnings')
            ->expectsOutput('No borrow transactions require deadline warnings.')
            ->assertExitCode(0);

        $this->assertEquals(0, Notification::count());
    }
}
