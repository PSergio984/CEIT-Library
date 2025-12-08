<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelper;

/**
 * Tests for BorrowTransaction factory data consistency.
 *
 * These tests ensure that the factory generates transactions with valid data:
 * - Started transactions: time_out is NULL, expires_at is in the future
 * - Overdue transactions: time_out is NULL, expires_at is in the past
 * - Completed transactions: time_out is set, time_out >= time_in
 * - Late return transactions: time_out is set, time_out > expires_at
 */
class BorrowTransactionFactoryConsistencyTest extends TestCase
{
    use RefreshDatabase, TestHelper;

    private User $user;

    private AcademicPaper $paper;

    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->paper = AcademicPaper::factory()->create();
        $this->inventory = Inventory::factory()->create([
            'academic_paper_id' => $this->paper->id,
            'copy_number' => 1,
            'status' => 'Available',
        ]);
    }

    /**
     * Test that factory default creates a completed transaction with valid time_out.
     */
    public function test_factory_default_creates_completed_transaction_with_time_out(): void
    {
        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $this->user->id,
            'academic_paper_id' => $this->paper->id,
            'inventory_id' => $this->inventory->id,
            'session_token' => $this->generateSessionToken(),
        ]);

        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->time_out, 'Completed transaction must have time_out set');
        $this->assertNotNull($transaction->time_in);
        $this->assertGreaterThanOrEqual(
            $transaction->time_in,
            $transaction->time_out,
            'time_out must be >= time_in for completed transactions'
        );
    }

    /**
     * Test that completed() state creates a valid completed transaction.
     */
    public function test_completed_state_creates_valid_transaction(): void
    {
        $transaction = BorrowTransaction::factory()
            ->completed()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->time_out, 'Completed transaction must have time_out set');
        $this->assertNotNull($transaction->time_in);
        $this->assertGreaterThanOrEqual(
            $transaction->time_in,
            $transaction->time_out,
            'time_out must be >= time_in for completed transactions'
        );
    }

    /**
     * Test that started() state creates a transaction with NULL time_out.
     */
    public function test_started_state_creates_transaction_with_null_time_out(): void
    {
        $transaction = BorrowTransaction::factory()
            ->started()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertEquals('started', $transaction->status);
        $this->assertNull($transaction->time_out, 'Started transaction must have NULL time_out');
        $this->assertNotNull($transaction->time_in);
        $this->assertNotNull($transaction->expires_at);
        $this->assertTrue(
            Carbon::parse($transaction->expires_at)->isFuture(),
            'Started transaction expires_at must be in the future'
        );
    }

    /**
     * Test that active() state is an alias for started() and creates valid data.
     */
    public function test_active_state_creates_transaction_with_null_time_out(): void
    {
        $transaction = BorrowTransaction::factory()
            ->active()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertEquals('started', $transaction->status);
        $this->assertNull($transaction->time_out, 'Active/Started transaction must have NULL time_out');
        $this->assertNotNull($transaction->time_in);
        $this->assertNotNull($transaction->expires_at);
        $this->assertTrue(
            Carbon::parse($transaction->expires_at)->isFuture(),
            'Active/Started transaction expires_at must be in the future'
        );
    }

    /**
     * Test that overdue() state creates a transaction with NULL time_out and past expires_at.
     */
    public function test_overdue_state_creates_transaction_with_null_time_out_and_past_expiry(): void
    {
        $transaction = BorrowTransaction::factory()
            ->overdue()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertEquals('overdue', $transaction->status);
        $this->assertNull($transaction->time_out, 'Overdue transaction must have NULL time_out (not yet returned)');
        $this->assertNotNull($transaction->time_in);
        $this->assertNotNull($transaction->expires_at);
        $this->assertTrue(
            Carbon::parse($transaction->expires_at)->isPast(),
            'Overdue transaction expires_at must be in the past'
        );
    }

    /**
     * Test that lateReturn() state creates a completed transaction returned after expiry.
     */
    public function test_late_return_state_creates_completed_transaction_returned_after_expiry(): void
    {
        $transaction = BorrowTransaction::factory()
            ->lateReturn()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->time_out, 'Late return transaction must have time_out set');
        $this->assertNotNull($transaction->time_in);
        $this->assertNotNull($transaction->expires_at);
        $this->assertGreaterThan(
            $transaction->expires_at,
            $transaction->time_out,
            'Late return time_out must be after expires_at'
        );
    }

    /**
     * Test that multiple started transactions all have NULL time_out.
     */
    public function test_multiple_started_transactions_all_have_null_time_out(): void
    {
        $transactions = [];
        for ($i = 0; $i < 5; $i++) {
            $inventory = Inventory::factory()->create([
                'academic_paper_id' => $this->paper->id,
                'copy_number' => $i + 2,
                'status' => 'Unavailable',
            ]);

            $transactions[] = BorrowTransaction::factory()
                ->started()
                ->create([
                    'user_id' => $this->user->id,
                    'academic_paper_id' => $this->paper->id,
                    'inventory_id' => $inventory->id,
                    'session_token' => $this->generateSessionToken("started-{$i}"),
                ]);
        }

        foreach ($transactions as $index => $transaction) {
            $this->assertEquals('started', $transaction->status, "Transaction {$index} should have status 'started'");
            $this->assertNull($transaction->time_out, "Transaction {$index} should have NULL time_out");
        }
    }

    /**
     * Test that multiple overdue transactions all have NULL time_out.
     */
    public function test_multiple_overdue_transactions_all_have_null_time_out(): void
    {
        $transactions = [];
        for ($i = 0; $i < 5; $i++) {
            $inventory = Inventory::factory()->create([
                'academic_paper_id' => $this->paper->id,
                'copy_number' => $i + 10,
                'status' => 'Unavailable',
            ]);

            $transactions[] = BorrowTransaction::factory()
                ->overdue()
                ->create([
                    'user_id' => $this->user->id,
                    'academic_paper_id' => $this->paper->id,
                    'inventory_id' => $inventory->id,
                    'session_token' => $this->generateSessionToken("overdue-{$i}"),
                ]);
        }

        foreach ($transactions as $index => $transaction) {
            $this->assertEquals('overdue', $transaction->status, "Transaction {$index} should have status 'overdue'");
            $this->assertNull($transaction->time_out, "Transaction {$index} should have NULL time_out");
        }
    }

    /**
     * Test that started transaction can be found by active transaction lookup.
     *
     * This test mimics the Return QR lookup that was failing before the fix.
     */
    public function test_started_transaction_can_be_found_by_active_lookup(): void
    {
        $transaction = BorrowTransaction::factory()
            ->started()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        // This is the lookup used in Return QR functionality
        $found = BorrowTransaction::where('inventory_id', $this->inventory->id)
            ->where('user_id', $this->user->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->first();

        $this->assertNotNull($found, 'Started transaction should be findable by active lookup');
        $this->assertEquals($transaction->id, $found->id);
    }

    /**
     * Test that overdue transaction can be found by active transaction lookup.
     */
    public function test_overdue_transaction_can_be_found_by_active_lookup(): void
    {
        $transaction = BorrowTransaction::factory()
            ->overdue()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        // This is the lookup used in Return QR functionality
        $found = BorrowTransaction::where('inventory_id', $this->inventory->id)
            ->where('user_id', $this->user->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->first();

        $this->assertNotNull($found, 'Overdue transaction should be findable by active lookup');
        $this->assertEquals($transaction->id, $found->id);
    }

    /**
     * Test that completed transaction is NOT found by active transaction lookup.
     */
    public function test_completed_transaction_is_not_found_by_active_lookup(): void
    {
        BorrowTransaction::factory()
            ->completed()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        // This is the lookup used in Return QR functionality
        $found = BorrowTransaction::where('inventory_id', $this->inventory->id)
            ->where('user_id', $this->user->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->first();

        $this->assertNull($found, 'Completed transaction should NOT be findable by active lookup');
    }

    /**
     * Test data consistency when changing state from started to completed.
     */
    public function test_transaction_state_progression_from_started_to_completed(): void
    {
        // Start with a started transaction
        $transaction = BorrowTransaction::factory()
            ->started()
            ->create([
                'user_id' => $this->user->id,
                'academic_paper_id' => $this->paper->id,
                'inventory_id' => $this->inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $this->assertNull($transaction->time_out);
        $this->assertEquals('started', $transaction->status);

        // Simulate returning the paper
        $returnTime = now();
        $transaction->update([
            'time_out' => $returnTime,
            'status' => 'completed',
        ]);

        $transaction->refresh();

        $this->assertNotNull($transaction->time_out);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals($returnTime->format('Y-m-d H:i:s'), $transaction->time_out->format('Y-m-d H:i:s'));
    }

    /**
     * Test that the factory does not create inconsistent data where status is started but time_out is set.
     */
    public function test_factory_does_not_create_inconsistent_started_with_time_out(): void
    {
        // Create many started transactions and verify none have time_out set
        for ($i = 0; $i < 10; $i++) {
            $inventory = Inventory::factory()->create([
                'academic_paper_id' => $this->paper->id,
                'copy_number' => $i + 100,
            ]);

            $transaction = BorrowTransaction::factory()
                ->started()
                ->create([
                    'user_id' => $this->user->id,
                    'academic_paper_id' => $this->paper->id,
                    'inventory_id' => $inventory->id,
                    'session_token' => $this->generateSessionToken("consistency-{$i}"),
                ]);

            $this->assertNull(
                $transaction->time_out,
                "Iteration {$i}: Started transaction created with time_out set - DATA INCONSISTENCY"
            );
        }
    }

    /**
     * Test that the factory does not create inconsistent data where status is overdue but time_out is set.
     */
    public function test_factory_does_not_create_inconsistent_overdue_with_time_out(): void
    {
        // Create many overdue transactions and verify none have time_out set
        for ($i = 0; $i < 10; $i++) {
            $inventory = Inventory::factory()->create([
                'academic_paper_id' => $this->paper->id,
                'copy_number' => $i + 200,
            ]);

            $transaction = BorrowTransaction::factory()
                ->overdue()
                ->create([
                    'user_id' => $this->user->id,
                    'academic_paper_id' => $this->paper->id,
                    'inventory_id' => $inventory->id,
                    'session_token' => $this->generateSessionToken("overdue-consistency-{$i}"),
                ]);

            $this->assertNull(
                $transaction->time_out,
                "Iteration {$i}: Overdue transaction created with time_out set - DATA INCONSISTENCY"
            );
        }
    }
}
