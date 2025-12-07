<?php

namespace App\Console\Commands;

use App\Mail\LibrarianAssignmentReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestLibrarianAlertFullWeek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:test-full-week {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email with entire week unassigned (6 days: Monday-Saturday)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");

            return Command::FAILURE;
        }

        $this->info("Preparing test email for: {$email}");

        // Create full week of unassigned dates (Monday through Saturday)
        $weekStart = Carbon::now()->addDays(3)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SATURDAY);

        $testUnassignedDates = collect([
            $weekStart->copy()->addDays(0), // Monday
            $weekStart->copy()->addDays(1), // Tuesday
            $weekStart->copy()->addDays(2), // Wednesday
            $weekStart->copy()->addDays(3), // Thursday
            $weekStart->copy()->addDays(4), // Friday
            $weekStart->copy()->addDays(5), // Saturday
        ]);

        $this->warn('Test scenario: ENTIRE WEEK UNASSIGNED (Critical Alert)');
        $this->line("  Week: {$weekStart->format('M d')} - {$weekEnd->format('M d, Y')}");
        $this->line('  Unassigned dates:');
        foreach ($testUnassignedDates as $date) {
            $this->line("    - {$date->format('l, F j, Y')}");
        }

        $this->newLine();
        $this->info('Sending test email...');

        try {
            Mail::to($email)->send(
                new LibrarianAssignmentReminder($testUnassignedDates, $weekStart, $weekEnd)
            );

            $this->info("✓ Test email sent successfully to: {$email}");
            $this->line('This email will trigger the critical alert warning (5+ unassigned days).');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("✗ Failed to send test email: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
