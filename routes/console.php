<?php

use App\Models\Attendance;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Check for missing timeouts (users who forgot to check out)
 * Creates a violation if user didn't timeout on the same day
 * Penalty: -10 credit score
 */
Artisan::command('attendance:check-missing-timeouts', function () {
    $this->info('Checking for missing timeouts...');

    // Get all active attendances that are from previous days
    $yesterday = Carbon::yesterday()->endOfDay();

    $missingSessions = Attendance::where('status', 'active')
        ->whereNotNull('time_in')
        ->whereNull('time_out')
        ->where('time_in', '<=', $yesterday)
        ->with('user')
        ->get();

    if ($missingSessions->isEmpty()) {
        $this->info('No missing timeouts found.');
        return 0;
    }

    // Get or create the "Missing Timeout" violation type
    $violation = Violation::firstOrCreate(
        ['name' => 'Missing Timeout'],
        [
            'description' => 'User forgot to check out from library session',
            'penalty_score' => 10,
        ]
    );

    // Preload all existing violation transactions for these sessions
    $existingViolations = ViolationTransaction::where('violation_id', $violation->id)
        ->whereIn('user_id', $missingSessions->pluck('user_id'))
        ->whereIn('attendance_id', $missingSessions->pluck('id'))
        ->get()
        ->map(function ($vt) {
            return $vt->user_id . '-' . $vt->violation_id . '-' . $vt->attendance_id;
        })->toArray();

    $count = 0;
    foreach ($missingSessions as $session) {
        try {
            DB::beginTransaction();
            $key = $session->user_id . '-' . $violation->id . '-' . $session->id;
            $existingViolation = in_array($key, $existingViolations);

            if (!$existingViolation) {
                ViolationTransaction::create([
                    'user_id' => $session->user_id,
                    'violation_id' => $violation->id,
                    'attendance_id' => $session->id,
                    'date_occurred' => $session->time_in->toDateString(),
                    'severity' => 'Minor',
                    'remarks' => ViolationTransaction::buildMissingTimeoutRemarks($session->id, $session->time_in),
                ]);

                $session->time_out = $session->time_in->copy()->endOfDay();
                $session->status = 'completed';
                $session->calculateDuration();
                $session->save();

                DB::commit();
                $count++;
                $this->line("Created violation for user {$session->user->email} (Session ID: {$session->id})");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to process session {$session->id}: {$e->getMessage()}");
        }
    }

    $this->info("Created {$count} violation(s) for missing timeouts.");
    return 0;
})->purpose('Check for missing timeouts and create violations');

// Schedule the command to run daily at 12:30 AM
Schedule::command('attendance:check-missing-timeouts')->dailyAt('00:30');
