<?php

namespace App\Console\Commands;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateLibrarianRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:update-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically assign/remove librarian roles based on batch duty dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = date('Y-m-d');
        $studentRoleId = Role::where('name', 'student')->value('id') ?? 1;
        $librarianRoleId = Role::where('name', 'librarian')->value('id') ?? 2;

        // Get all batches assigned to today (start_date is today and no end_date or end_date is in the future)
        $todayBatches = Librarian::whereNotNull('start_date')
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>', $today);
            })
            ->get()
            ->groupBy('batch_no');

        $promotedCount = 0;
        foreach ($todayBatches as $batchNo => $batch) {
            $userIds = $batch->pluck('user_id')->toArray();

            // Promote students to librarian role
            User::whereIn('id', $userIds)
                ->where('role_id', $studentRoleId)
                ->update(['role_id' => $librarianRoleId]);

            $promotedCount += count($userIds);

            // Update batch status to active
            Librarian::where('batch_no', $batchNo)
                ->update(['status' => 'active']);
        }

        // Get all expired batches (end_date is in the past OR start_date is in the past with no active period)
        $expiredBatches = Librarian::whereNotNull('start_date')
            ->where(function ($q) use ($today) {
                // Has end_date and it's in the past
                $q->whereDate('end_date', '<', $today)
                  // OR start_date was in the past but no longer active
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereDate('start_date', '<', $today)
                            ->where('status', 'active');
                    });
            })
            ->get()
            ->groupBy('batch_no');

        $demotedCount = 0;
        foreach ($expiredBatches as $batchNo => $batch) {
            $userIds = $batch->pluck('user_id')->toArray();

            // Demote librarians back to student role
            User::whereIn('id', $userIds)
                ->where('role_id', $librarianRoleId)
                ->update(['role_id' => $studentRoleId]);

            $demotedCount += count($userIds);

            // Update batch status to expired
            Librarian::where('batch_no', $batchNo)
                ->update(['status' => 'expired']);
        }

        // Mark future batches as inactive
        Librarian::whereNotNull('start_date')
            ->whereDate('start_date', '>', $today)
            ->update(['status' => 'inactive']);

        $this->info("Promoted {$promotedCount} students to librarian role");
        $this->info("Demoted {$demotedCount} librarians back to student role");
        $this->info('Librarian roles updated successfully!');

        return Command::SUCCESS;
    }
}
