<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use Illuminate\Console\Command;

class MarkOverdueTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:mark-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark borrow transactions as overdue if they exceed 3-hour limit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue transactions...');

        // Find all started transactions that are past their expiry time
        $overdueTransactions = BorrowTransaction::where('status', 'started')
            ->where('expires_at', '<', now())
            ->whereNull('time_out')
            ->get();

        $count = 0;
        foreach ($overdueTransactions as $transaction) {
            // This will trigger the model observer which sends notification
            $transaction->update(['status' => 'overdue']);
            $count++;

            $this->line("Marked transaction #{$transaction->id} as overdue for user {$transaction->user->first_name} {$transaction->user->last_name}");
        }

        if ($count > 0) {
            $this->info("✓ Marked {$count} transaction(s) as overdue");
        } else {
            $this->info('No overdue transactions found');
        }

        return Command::SUCCESS;
    }
}
