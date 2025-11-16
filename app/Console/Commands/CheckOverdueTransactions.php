<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Notifications\BorrowTransactionOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Check for overdue borrow transactions and send notifications
 * 
 * This command runs hourly via Laravel's task scheduler to:
 * 1. Find all 'started' transactions that are past their expiration date
 * 2. Update their status to 'overdue'
 * 3. Send overdue notification emails to affected users
 * 
 * Scheduled in: routes/console.php
 */
class CheckOverdueTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:check-overdue 
                            {--dry-run : Run without making changes or sending notifications}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue borrow transactions and send email notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Acquire lock to prevent concurrent execution
        $lock = Cache::lock('check-overdue-transactions', 300);

        if (!$lock->get()) {
            $this->warn('⚠️  Another instance of this command is already running. Exiting.');
            Log::warning('CheckOverdueTransactions command skipped - another instance is running');
            return self::FAILURE;
        }

        try {
            return $this->processOverdueTransactions();
        } finally {
            $lock->forceRelease();
        }
    }

    /**
     * Process overdue transactions
     */
    protected function processOverdueTransactions(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Checking for overdue borrow transactions...');
        $this->newLine();

        // Find all started or overdue transactions that are past expiration and have not been notified
        // This allows retrying failed notifications on subsequent runs
        $overdueTransactions = BorrowTransaction::with(['user', 'inventory.academicPaper'])
            ->whereIn('status', ['started', 'overdue'])
            ->where('expires_at', '<', now())
            ->whereNull('overdue_notified_at')
            ->get();

        if ($overdueTransactions->isEmpty()) {
            $this->info('✅ No overdue transactions found.');
            return self::SUCCESS;
        }

        $count = $overdueTransactions->count();
        $this->warn("⚠️  Found {$count} overdue " . \Illuminate\Support\Str::plural('transaction', $count));
        $this->newLine();

        // Display overdue transactions in a table
        $this->table(
            ['ID', 'User', 'Paper', 'Due Date', 'Overdue By'],
            $overdueTransactions->map(function ($transaction) {
                return [
                    $transaction->id,
                    $transaction->user->full_name ?? 'N/A',
                    \Illuminate\Support\Str::limit($transaction->inventory->academicPaper->title ?? 'N/A', 40),
                    $transaction->expires_at->format('Y-m-d H:i'),
                    $transaction->expires_at->diffForHumans(),
                ];
            })
        );

        if ($isDryRun) {
            $this->newLine();
            $this->info('🏃 Dry run mode - no changes made, no notifications sent.');
            return self::SUCCESS;
        }

        // Confirm before proceeding (unless --force is used)
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to update these transactions and send notifications?', true)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('📧 Processing overdue transactions...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        // Mutually exclusive outcome counters
        $successful = 0;
        $failedUpdate = 0;
        $failedNotification = 0;
        $noUser = 0;

        foreach ($overdueTransactions as $transaction) {
            $updateSucceeded = false;
            $notificationSucceeded = false;
            $userMissing = false;

            // Try to update status to overdue
            try {
                \DB::transaction(function () use ($transaction) {
                    $transaction->update(['status' => 'overdue']);
                });
                $updateSucceeded = true;

                // Log the action after DB commit
                Log::info('Overdue transaction status updated', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'paper_id' => $transaction->academic_paper_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update overdue transaction status', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);

                $this->newLine();
                $this->error("Failed to update transaction #{$transaction->id}: {$e->getMessage()}");
            }

            // If update succeeded, try to send notification
            if ($updateSucceeded) {
                if ($transaction->user) {
                    try {
                        $transaction->user->notify(new \App\Notifications\BorrowTransactionOverdue($transaction));
                        // Only mark as notified if notification succeeds - this allows retry on failure
                        $transaction->update(['overdue_notified_at' => now()]);
                        $notificationSucceeded = true;
                    } catch (\Exception $notifyEx) {
                        // Don't set overdue_notified_at on failure - transaction will be picked up on next run
                        Log::error('Failed to send overdue notification', [
                            'transaction_id' => $transaction->id,
                            'user_id' => $transaction->user_id,
                            'error' => $notifyEx->getMessage(),
                        ]);
                        $this->newLine();
                        $this->error("Failed to notify user for transaction #{$transaction->id}: {$notifyEx->getMessage()}");
                    }
                } else {
                    $userMissing = true;
                    Log::warning('Cannot notify user - user not found', [
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->user_id,
                    ]);
                }
            }

            // Increment mutually exclusive counters based on final outcome
            if (!$updateSucceeded) {
                $failedUpdate++;
            } elseif ($userMissing) {
                $noUser++;
            } elseif ($notificationSucceeded) {
                $successful++;
            } else {
                $failedNotification++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✅ Processing complete!');
        $this->newLine();
        $this->line("📊 Summary:");
        $this->line("   • Successful (updated + notified): {$successful}");
        $this->line("   • Failed to update status: {$failedUpdate}");
        $this->line("   • Failed to send notification: {$failedNotification}");
        $this->line("   • No user found: {$noUser}");

        return self::SUCCESS;
    }
}
