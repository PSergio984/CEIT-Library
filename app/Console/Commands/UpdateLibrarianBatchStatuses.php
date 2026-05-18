<?php

namespace App\Console\Commands;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateLibrarianBatchStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:update-batch-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update librarian batch statuses based on current date (inactive -> active -> expired)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating librarian batch statuses...');

        $today = date('Y-m-d');

        DB::transaction(function () use ($today) {
            // Get student and librarian role IDs
            $studentRoleId = Role::where('name', 'student')->value('id') ?? 1;
            $librarianRoleId = Role::where('name', 'librarian')->value('id') ?? 2;

            // Update INACTIVE batches to ACTIVE if their start date is today
            $inactiveBatches = Librarian::where('status', 'inactive')
                ->whereNotNull('start_date')
                ->where('start_date', '<=', $today)
                ->get()
                ->groupBy('batch_no');

            $activatedCount = 0;
            foreach ($inactiveBatches as $batchNo => $librarians) {
                // Update batch status to active
                Librarian::where('batch_no', $batchNo)->update(['status' => 'active']);

                // Change user roles to librarian
                $userIds = $librarians->pluck('user_id');
                User::whereIn('id', $userIds)->update(['role_id' => $librarianRoleId]);

                $activatedCount++;
                $this->info("  ✓ Activated batch {$batchNo} ({$librarians->count()} students)");
            }

            // Update ACTIVE batches to EXPIRED if their end date has passed OR if it's past their start date
            $activeBatches = Librarian::where('status', 'active')
                ->whereNotNull('start_date')
                ->where(function ($query) use ($today) {
                    // Either has end_date in the past, or start_date is before today (one-day duty)
                    $query->where(function ($q) use ($today) {
                        $q->whereNotNull('end_date')
                            ->where('end_date', '<', $today);
                    })
                        ->orWhere(function ($q) use ($today) {
                            $q->whereNull('end_date')
                                ->where('start_date', '<', $today);
                        });
                })
                ->get()
                ->groupBy('batch_no');

            $expiredCount = 0;
            foreach ($activeBatches as $batchNo => $librarians) {
                // Update batch status to expired
                Librarian::where('batch_no', $batchNo)->update(['status' => 'expired']);

                // Change user roles back to student
                $userIds = $librarians->pluck('user_id');
                User::whereIn('id', $userIds)
                    ->where('role_id', $librarianRoleId)
                    ->update(['role_id' => $studentRoleId]);

                $expiredCount++;
                $this->info("  ✓ Expired batch {$batchNo} ({$librarians->count()} students reverted to student role)");
            }
        });

        $this->info('✓ Batch status update completed!');

        return 0;
    }
}
