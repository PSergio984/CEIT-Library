<?php

namespace App\Console\Commands;

use App\Mail\LibrarianAssignmentReminder;
use App\Models\Librarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckLibrarianAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:check-assignments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for unassigned librarian duty days and send email alerts to admins 3 days in advance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for unassigned librarian duty days...');

        // Check the upcoming week starting 3 days from now
        $checkStart = Carbon::now()->addDays(3)->startOfWeek(Carbon::MONDAY);
        $checkEnd = $checkStart->copy()->endOfWeek(Carbon::SATURDAY);

        $this->info("Checking week: {$checkStart->format('Y-m-d')} to {$checkEnd->format('Y-m-d')}");

        // Get all dates that have assigned librarians
        $assignedDates = Librarian::whereNotNull('start_date')
            ->whereBetween('start_date', [$checkStart, $checkEnd])
            ->pluck('start_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->toArray();

        $this->info('Assigned dates: '.(empty($assignedDates) ? 'None' : implode(', ', $assignedDates)));

        // Generate all weekday dates (Monday to Saturday, excluding Sunday)
        $allWeekdayDates = [];
        $currentDate = $checkStart->copy();

        while ($currentDate <= $checkEnd) {
            // Skip Sundays (day of week = 0)
            if ($currentDate->dayOfWeek !== Carbon::SUNDAY) {
                $allWeekdayDates[] = $currentDate->copy();
            }
            $currentDate->addDay();
        }

        // Find unassigned dates (only future dates)
        $unassignedDates = collect($allWeekdayDates)->filter(function ($date) use ($assignedDates) {
            return ! in_array($date->format('Y-m-d'), $assignedDates) && $date->isFuture();
        })->values();

        if ($unassignedDates->isEmpty()) {
            $this->info('✓ All weekdays for the upcoming week are assigned. No alerts needed.');

            Log::info('Librarian assignment check completed - all dates assigned', [
                'week_start' => $checkStart->format('Y-m-d'),
                'week_end' => $checkEnd->format('Y-m-d'),
            ]);

            return Command::SUCCESS;
        }

        $this->warn("Found {$unassignedDates->count()} unassigned date(s):");
        foreach ($unassignedDates as $date) {
            $daysUntil = $date->diffInDays(now());
            $urgency = $daysUntil <= 3 ? ' (URGENT)' : '';
            $this->line("  - {$date->format('l, F j, Y')} - {$daysUntil} days away{$urgency}");
        }

        // Get all active admins and super admins
        $admins = User::whereHas('role', function ($query) {
            $query->whereIn('name', ['admin', 'super_admin']);
        })
            ->where('account_status', 'active')
            ->get();

        if ($admins->isEmpty()) {
            $this->error('✗ No active admins found to send alerts to.');

            Log::error('Librarian assignment check - no admins found', [
                'unassigned_dates' => $unassignedDates->count(),
            ]);

            return Command::FAILURE;
        }

        $this->info("Sending alerts to {$admins->count()} admin(s)...");

        $successCount = 0;
        $failCount = 0;

        // Send emails to all admins
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(
                    new LibrarianAssignmentReminder($unassignedDates, $checkStart, $checkEnd)
                );

                $this->info("  ✓ Alert sent to: {$admin->email} ({$admin->first_name} {$admin->last_name})");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to send to {$admin->email}: {$e->getMessage()}");
                $failCount++;

                Log::error('Failed to send librarian assignment alert', [
                    'admin_email' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Email sending complete: {$successCount} successful, {$failCount} failed");

        Log::info('Librarian assignment alerts sent', [
            'week_start' => $checkStart->format('Y-m-d'),
            'week_end' => $checkEnd->format('Y-m-d'),
            'unassigned_dates' => $unassignedDates->count(),
            'admins_notified' => $successCount,
            'failed_notifications' => $failCount,
        ]);

        return Command::SUCCESS;
    }
}
