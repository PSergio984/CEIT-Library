<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use App\Notifications\BorrowTransactionOverdue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckOverdueTransactionsTest extends TestCase
{
    private function makeOverdueTransaction(User $user, array $overrides = []): BorrowTransaction
    {
        $paper = AcademicPaper::factory()->create();

        return BorrowTransaction::factory()->create(array_merge([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => Inventory::factory()->create([
                'academic_paper_id' => $paper->id,
                'copy_number' => 1,
            ])->id,
            'status' => 'started',
            'time_in' => Carbon::now()->subDays(3),
            'time_out' => null,
            'expires_at' => Carbon::now()->subDay(),
            'overdue_notified_at' => null,
        ], $overrides));
    }

    public function test_overdue_transaction_notifies_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $transaction = $this->makeOverdueTransaction($user);

        $this->artisan('transactions:check-overdue', ['--force' => true])
            ->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            BorrowTransactionOverdue::class,
            fn ($notification) => $notification->transaction->id === $transaction->id
        );

        $transaction->refresh();
        $this->assertSame('overdue', $transaction->status);
        $this->assertNotNull($transaction->overdue_notified_at);
    }

    public function test_transaction_with_missing_user_is_counted_and_skipped(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $transaction = $this->makeOverdueTransaction($user);

        // Simulate a row whose user_id points at a missing user. The FK check
        // is deferred to commit, which never happens (test transaction rolls
        // back), so the relation resolves to null for this row.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('borrow_transactions')
            ->where('id', $transaction->id)
            ->update(['user_id' => PHP_INT_MAX]);

        $this->artisan('transactions:check-overdue', ['--force' => true])
            ->expectsOutputToContain('No user found: 1')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_overdue_notification_mail_and_array_payloads(): void
    {
        $user = User::factory()->create();
        $transaction = $this->makeOverdueTransaction($user);
        $notification = new BorrowTransactionOverdue($transaction);

        $this->assertSame(['mail', 'database'], $notification->via($user));

        $mail = $notification->toMail($user);
        $this->assertStringContainsString('Overdue', $mail->subject);

        $payload = $notification->toArray($user);
        $this->assertSame($transaction->id, $payload['transaction_id']);
        $this->assertSame($transaction->expires_at->toDateTimeString(), $payload['due_date']);
    }
}
