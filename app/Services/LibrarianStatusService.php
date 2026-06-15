<?php

namespace App\Services;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LibrarianStatusService
{
    /**
     * Synchronize batch statuses and user roles based on current date.
     */
    public function syncAllBatches(): void
    {
        $today = date('Y-m-d');

        DB::transaction(function () use ($today) {
            $this->activateDueBatches($today);
            $this->expirePastBatches($today);
        });
    }

    /**
     * Activate batches that should be active today.
     */
    private function activateDueBatches(string $today): void
    {
        $librarianRoleId = Role::where('name', Role::LIBRARIAN)->value('id') ?? 2;

        $inactiveBatches = Librarian::where('status', 'inactive')
            ->whereNotNull('start_date')
            ->where('start_date', '<=', $today)
            ->get()
            ->groupBy('batch_no');

        foreach ($inactiveBatches as $batchNo => $librarians) {
            Librarian::where('batch_no', $batchNo)->update(['status' => 'active']);

            $userIds = $librarians->pluck('user_id');
            User::whereIn('id', $userIds)->update(['role_id' => $librarianRoleId]);

            foreach ($librarians as $librarian) {
                $dutyDate = date('F j, Y', strtotime($librarian->start_date));
                app(NotificationService::class)->notify(
                    $librarian->user,
                    'librarian_activated',
                    'Your Librarian Batch is Now Active',
                    "Your librarian batch #{$batchNo} is now active. Your duty date is today, {$dutyDate}. You can now perform librarian duties.",
                    [
                        'batch_no' => $batchNo,
                        'start_date' => $librarian->start_date,
                    ]
                );
            }
        }
    }

    /**
     * Expire batches whose duty period has ended.
     */
    private function expirePastBatches(string $today): void
    {
        $studentRoleId = Role::where('name', Role::STUDENT)->value('id') ?? 1;
        $librarianRoleId = Role::where('name', Role::LIBRARIAN)->value('id') ?? 2;

        $activeBatches = Librarian::where('status', 'active')
            ->whereNotNull('start_date')
            ->where(function ($query) use ($today) {
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

        foreach ($activeBatches as $batchNo => $librarians) {
            Librarian::where('batch_no', $batchNo)->update(['status' => 'expired']);

            $userIds = $librarians->pluck('user_id');
            User::whereIn('id', $userIds)
                ->where('role_id', $librarianRoleId)
                ->update(['role_id' => $studentRoleId]);
        }
    }

    /**
     * Update a specific batch's data and immediately sync its status/roles.
     */
    public function updateBatch(string $batchNo, array $data): void
    {
        DB::transaction(function () use ($batchNo, $data) {
            Librarian::where('batch_no', $batchNo)->update($data);
            $this->syncAllBatches();
        });
    }
}
