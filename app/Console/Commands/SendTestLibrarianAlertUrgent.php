<?php

namespace App\Console\Commands;

use App\Mail\LibrarianAssignmentReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestLibrarianAlertUrgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:test-urgent {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email with urgent dates (TODAY, TOMORROW tags)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");
            return Command::FAILURE;
        }

        $this->info("Preparing test email for: {$email}");

        // Create urgent scenario with dates very close to today
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SATURDAY);

        $testUnassignedDates = collect([
            Carbon::today(),              // TODAY
            Carbon::tomorrow(),           // TOMORROW
            Carbon::now()->addDays(2),    // 2 DAYS
        ]);

        $this->warn('Test scenario: URGENT - Immediate dates unassigned');
        $this->line("  Week: {$weekStart->format('M d')} - {$weekEnd->format('M d, Y')}");
        $this->line('  Unassigned dates:');
        foreach ($testUnassignedDates as $date) {
            $daysAway = $date->diffInDays(now());
            $urgency = $daysAway == 0 ? 'TODAY' : ($daysAway == 1 ? 'TOMORROW' : "{$daysAway} DAYS");
            $this->line("    - {$date->format('l, F j, Y')} [{$urgency}]");
        }

        $this->newLine();
        $this->info('Sending test email...');

        try {
            Mail::to($email)->send(
                new LibrarianAssignmentReminder($testUnassignedDates, $weekStart, $weekEnd)
            );

            $this->info("✓ Test email sent successfully to: {$email}");
            $this->line('This email will show urgent tags (TODAY, TOMORROW, X DAYS).');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("✗ Failed to send test email: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
