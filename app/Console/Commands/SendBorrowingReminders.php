<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBorrowingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily borrowing reminders for papers due in 3 days or overdue.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $service): int
    {
        $this->sendDueSoonReminders($service);
        $this->sendOverdueReminders($service);

        return 0;
    }

    protected function sendDueSoonReminders(NotificationService $service): void
    {
        $this->info('Checking for due-soon transactions...');

        $threeDaysFromNowStart = Carbon::now()->addDays(3)->startOfDay();
        $threeDaysFromNowEnd = Carbon::now()->addDays(3)->endOfDay();

        $transactions = BorrowTransaction::query()
            ->where('status', 'started')
            ->whereNull('reminder_notified_at')
            ->whereBetween('expires_at', [$threeDaysFromNowStart, $threeDaysFromNowEnd])
            ->with(['academicPaper', 'user'])
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions due in 3 days.');

            return;
        }

        foreach ($transactions as $transaction) {
            try {
                $service->notify(
                    $transaction->user,
                    'reminders',
                    'Return Reminder',
                    "Your borrowed material \"{$transaction->academicPaper->title}\" is due in 3 days. Please return it by ".$transaction->expires_at->format('M d, Y h:i A').' to avoid penalties.',
                    [
                        'transaction_id' => $transaction->id,
                        'paper_title' => $transaction->academicPaper->title,
                        'expires_at' => $transaction->expires_at->format('M d, Y h:i A'),
                        'url' => '/student/dashboard',
                    ]
                );

                $transaction->update(['reminder_notified_at' => now()]);
            } catch (\Throwable $e) {
                \Log::error("Failed to send due-soon reminder for transaction {$transaction->id}: ".$e->getMessage());
            }
        }

        $this->info('Sent '.$transactions->count().' due-soon reminders.');
    }

    protected function sendOverdueReminders(NotificationService $service): void
    {
        $this->info('Checking for daily overdue follow-ups...');

        $transactions = BorrowTransaction::query()
            ->where('status', 'overdue')
            ->where('expires_at', '<', now()->subDay())
            ->where(function ($query) {
                $query->whereNull('overdue_notified_at')
                    ->orWhere('overdue_notified_at', '<', now()->startOfDay());
            })
            ->with(['academicPaper', 'user'])
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No overdue transactions needing daily follow-up.');

            return;
        }

        foreach ($transactions as $transaction) {
            try {
                $service->notify(
                    $transaction->user,
                    'reminders',
                    'OVERDUE NOTICE',
                    "Your borrowed material \"{$transaction->academicPaper->title}\" is OVERDUE. Please return it immediately to avoid further penalties.",
                    [
                        'transaction_id' => $transaction->id,
                        'paper_title' => $transaction->academicPaper->title,
                        'due_date' => $transaction->expires_at->format('M d, Y h:i A'),
                        'url' => '/student/dashboard',
                    ]
                );

                $transaction->update(['overdue_notified_at' => now()]);
            } catch (\Throwable $e) {
                \Log::error("Failed to send overdue reminder for transaction {$transaction->id}: ".$e->getMessage());
            }
        }

        $this->info('Sent '.$transactions->count().' overdue follow-up reminders.');
    }
}
