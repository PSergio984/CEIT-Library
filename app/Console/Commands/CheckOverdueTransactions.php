<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Notifications\BorrowTransactionOverdue;
use Illuminate\Console\Command;
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
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Checking for overdue borrow transactions...');
        $this->newLine();

        // Find all started transactions that are past expiration
        $overdueTransactions = BorrowTransaction::with(['user', 'inventory.academicPaper'])
            ->where('status', 'started')
            ->where('expires_at', '<', now())
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

        $updated = 0;
        $notified = 0;
        $failed = 0;

        foreach ($overdueTransactions as $transaction) {

            try {
                \DB::transaction(function () use ($transaction, &$updated) {
                    // Update status to overdue
                    $transaction->update(['status' => 'overdue']);
                    $updated++;
                });

                // Log the action after DB commit
                \Log::info('Overdue transaction processed', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'paper_id' => $transaction->academic_paper_id,
                ]);

                // Send notification to user (outside transaction)
                if ($transaction->user) {
                    try {
                        $transaction->user->notify(new \App\Notifications\BorrowTransactionOverdue($transaction));
                        $notified++;
                    } catch (\Exception $notifyEx) {
                        $failed++;
                        \Log::error('Failed to send overdue notification', [
                            'transaction_id' => $transaction->id,
                            'user_id' => $transaction->user_id,
                            'error' => $notifyEx->getMessage(),
                        ]);
                        $this->newLine();
                        $this->error("Failed to notify user for transaction #{$transaction->id}: {$notifyEx->getMessage()}");
                    }
                } else {
                    \Log::warning('Cannot notify user - user not found', [
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->user_id,
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Failed to process overdue transaction', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);

                $this->newLine();
                $this->error("Failed to process transaction #{$transaction->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✅ Processing complete!');
        $this->newLine();
        $this->line("📊 Summary:");
        $this->line("   • Transactions updated: {$updated}");
        $this->line("   • Notifications sent: {$notified}");

        if ($failed > 0) {
            $this->line("   • Failed: {$failed}");
        }

        return self::SUCCESS;
    }
}
