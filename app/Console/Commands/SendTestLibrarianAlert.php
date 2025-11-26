<?php

namespace App\Console\Commands;

use App\Mail\LibrarianAssignmentReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestLibrarianAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'librarian:test-alert {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test librarian assignment alert email to a specific email address';

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

        // Create sample unassigned dates for testing (next week)
        $weekStart = Carbon::now()->addDays(3)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SATURDAY);

        $testUnassignedDates = collect([
            $weekStart->copy()->addDays(0), // Monday
            $weekStart->copy()->addDays(2), // Wednesday
            $weekStart->copy()->addDays(4), // Friday
        ]);

        $this->info('Test scenario: 3 unassigned dates');
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
            $this->line('Check the inbox (and spam folder) for the email.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("✗ Failed to send test email: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
