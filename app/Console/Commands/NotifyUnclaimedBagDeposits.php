<?php

namespace App\Console\Commands;

use App\Models\BagDeposit;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class NotifyUnclaimedBagDeposits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bag:notify-unclaimed
                            {--dry-run : List unclaimed deposits without notifying or stamping}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify depositors of bag deposits still open at closing; re-pings daily until claimed';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $service): int
    {
        $this->info('Checking for unclaimed bag deposits...');

        // unclaimed_notified_at stamped today = already pinged; anything
        // older (or null) is fair game — permits the next day's re-ping.
        $deposits = BagDeposit::query()
            ->open()
            ->where(function ($query) {
                $query->whereNull('unclaimed_notified_at')
                    ->orWhere('unclaimed_notified_at', '<', now()->startOfDay());
            })
            ->with(['user', 'slot'])
            ->get();

        if ($deposits->isEmpty()) {
            $this->info('No unclaimed bag deposits.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no notifications sent, no stamps written.');
            $this->table(
                ['Deposit', 'Depositor', 'Slot', 'Deposited at'],
                $deposits->map(fn ($d) => [
                    $d->id,
                    $d->user->first_name.' '.$d->user->last_name,
                    $d->slot->number,
                    $d->created_at->format('M d, Y g:i A'),
                ])
            );

            return 0;
        }

        $notified = 0;

        foreach ($deposits as $deposit) {
            // A claim landing between the query and this notification must
            // not be told their bag is still at the counter.
            if (! $deposit->refresh()->isOpen()) {
                continue;
            }

            try {
                $service->notify(
                    $deposit->user,
                    'bag_unclaimed',
                    'Unclaimed bag deposit',
                    "Your bag deposit in slot {$deposit->slot->number} ({$deposit->item_count} item".($deposit->item_count > 1 ? 's' : '').') is still at the counter. Please claim it tomorrow — unclaimed deposits are re-reported nightly.',
                    [
                        'bag_deposit_id' => $deposit->id,
                        'slot_number' => $deposit->slot->number,
                        'url' => '/student/dashboard',
                    ]
                );

                $deposit->update(['unclaimed_notified_at' => now()]);
                $notified++;
            } catch (\Throwable $e) {
                \Log::error("Failed to notify unclaimed bag deposit {$deposit->id}: ".$e->getMessage());
            }
        }

        $this->info("Sent {$notified} unclaimed-deposit notification(s).");

        return 0;
    }
}
