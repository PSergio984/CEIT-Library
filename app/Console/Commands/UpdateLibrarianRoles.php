<?php

namespace App\Console\Commands;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        // Get all batches assigned to today
        $todayBatches = Librarian::whereNotNull('date_start')
            ->whereDate('date_start', $today)
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

        // Get all batches from past dates (not today)
        $pastBatches = Librarian::whereNotNull('date_start')
            ->whereDate('date_start', '<', $today)
            ->get()
            ->groupBy('batch_no');

        $demotedCount = 0;
        foreach ($pastBatches as $batchNo => $batch) {
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
        Librarian::whereNotNull('date_start')
            ->whereDate('date_start', '>', $today)
            ->update(['status' => 'inactive']);

        $this->info("✅ Promoted {$promotedCount} students to librarian role");
        $this->info("✅ Demoted {$demotedCount} librarians back to student role");
        $this->info("✅ Librarian roles updated successfully!");

        return Command::SUCCESS;
    }
}
