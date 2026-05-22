<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDeadlineWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:send-deadline-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send database and web push notifications to students whose borrowing transactions expire in less than 30 minutes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $warningLimit = Carbon::now()->addMinutes(30);

        // Fetch transactions starting status, not yet notified, and expiring within the next 30 minutes
        $transactions = BorrowTransaction::query()
            ->where('status', 'started')
            ->whereNull('warning_notified_at')
            ->whereBetween('expires_at', [$now, $warningLimit])
            ->with(['academicPaper', 'user'])
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No borrow transactions require deadline warnings.');

            return 0;
        }

        $count = 0;
        foreach ($transactions as $transaction) {
            Notification::create([
                'user_id' => $transaction->user_id,
                'type' => 'paper_deadline_warning',
                'title' => 'Deadline Approaching!',
                'message' => "Your borrowed material \"{$transaction->academicPaper->title}\" is due in less than 30 minutes. Please return it to avoid a penalty.",
                'data' => [
                    'transaction_id' => $transaction->id,
                    'paper_title' => $transaction->academicPaper->title,
                    'expires_at' => $transaction->expires_at->format('M d, Y h:i A'),
                    'url' => '/student/dashboard',
                ],
            ]);

            $transaction->update([
                'warning_notified_at' => now(),
            ]);

            $count++;
        }

        $this->info("Sent {$count} deadline warnings successfully.");

        return 0;
    }
}
