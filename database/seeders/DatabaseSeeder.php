<?php

namespace Database\Seeders;

use App\Models\RuleHeader;
use App\Models\User;
use App\Models\AcademicPaper;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use App\Models\ScoreIncrement;
use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\Inventory;
use App\Models\BorrowTransaction;
use App\Models\RuleRegulation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin users
        $admin = User::factory()->create([
            'first_name' => 'Janrel',
            'last_name' => 'Motovlogs',
            'email' => 'admin@plv.edu.ph',
            'is_admin' => true,
            'password' => bcrypt('Pwd@12345'),
        ]);

        $librarian = User::factory()->create([
            'first_name' => 'Librarian',
            'last_name' => 'Librarian',
            'email' => 'librarian@plv.edu.ph',
            'is_admin' => false,
            'password' => bcrypt('Pwd@12345'),
        ]);

        Librarian::factory()->active()->create([
            'user_id' => $librarian->id,
            'created_by' => $admin->id,
        ]);

        // Create regular student users
        $students = User::factory(50)->create();

        // Create violations (predefined set)
        $violationData = [
            ['name' => 'Late Return of Books', 'description' => 'Returning library books beyond the due date', 'penalty_score' => 5],
            ['name' => 'Loud Talking in Library', 'description' => 'Making excessive noise that disturbs other library users', 'penalty_score' => 3],
            ['name' => 'Eating in Library', 'description' => 'Consuming food inside the library premises', 'penalty_score' => 4],
            ['name' => 'Using Mobile Phone Loudly', 'description' => 'Taking calls or playing media without headphones', 'penalty_score' => 3],
            ['name' => 'Damaging Library Property', 'description' => 'Causing damage to books, furniture, or equipment', 'penalty_score' => 15],
            ['name' => 'Smoking in Library', 'description' => 'Smoking or vaping inside the library building', 'penalty_score' => 20],
            ['name' => 'Bringing Prohibited Items', 'description' => 'Bringing weapons, alcohol, or other prohibited items', 'penalty_score' => 25],
            ['name' => 'Theft of Library Materials', 'description' => 'Stealing books or library property', 'penalty_score' => 30],
            ['name' => 'Inappropriate Behavior', 'description' => 'Engaging in disruptive or inappropriate conduct', 'penalty_score' => 10],
            ['name' => 'Unauthorized Entry', 'description' => 'Entering restricted areas without permission', 'penalty_score' => 12],
        ];

        foreach ($violationData as $violation) {
            Violation::create($violation);
        }

        // Create academic papers
        $academicPapers = AcademicPaper::factory(30)->create();

        // Create authors
        $authors = \App\Models\Author::factory(20)->create();

        // Seed the academic_paper_authors pivot table
        $now = now();
        foreach ($academicPapers as $paper) {
            // Attach 1-3 random authors to each paper with timestamps
            $randomAuthors = $authors->random(rand(1, 3))->pluck('id')->toArray();
            $attachData = [];
            foreach ($randomAuthors as $authorId) {
                $attachData[$authorId] = ['created_at' => $now, 'updated_at' => $now];
            }
            $paper->authors()->attach($attachData);
        }

        // Create copies for each academic paper
        $academicPapers->each(function ($academicPaper) {
            // Random number of copies per academic paper (1-4 copies)
            $copyCount = fake()->numberBetween(1, 4);

            for ($i = 1; $i <= $copyCount; $i++) {
                Inventory::factory()->create([
                    'academic_paper_id' => $academicPaper->id,
                    'copy_number' => $i,
                    'status' => fake()->randomElement(['Available', 'Reserved', 'Unavailable']),
                ]);
            }
        });

        // Ensure we have some available copies for testing
        $availableAcademicPapers = $academicPapers->take(10);
        $availableAcademicPapers->each(function ($academicPaper) {
            // Make sure at least one copy is available
            $firstCopy = $academicPaper->copies()->first();
            if ($firstCopy) {
                $firstCopy->update(['status' => 'Available']);
            }
        });

        // Create credit scores for all users (including Admin)
        $allUsers = User::all();
        foreach ($allUsers as $user) {
            ScoreIncrement::factory()->create(['user_id' => $user->id]);
        }

        // Create some user violations for random students
        $violations = Violation::all();
        $someStudents = $students->random(15); // 15 students with violations

        foreach ($someStudents as $student) {
            // Each student gets 1-3 violations
            $violationCount = rand(1, 3);
            $randomViolations = $violations->random($violationCount);

            foreach ($randomViolations as $violation) {
                ViolationTransaction::factory()->create([
                    'user_id' => $student->id,
                    'violation_id' => $violation->id,
                ]);
            }

            // Update credit score based on violations
            $creditScore = ScoreIncrement::where('user_id', $student->id)->first();
            if ($creditScore) {
                $creditScore->updateScore();
            }
        }

        // Create active librarians (students on duty today)
        $librarianStudents = $students->random(3); // 3 students on librarian duty
        foreach ($librarianStudents as $student) {
            Librarian::factory()->active()->create([
                'user_id' => $student->id,
                'created_by' => $admin->id,
            ]);
        }

        // Create some expired librarian duties from previous days
        $remainingStudents = $students->diff($librarianStudents); // Exclude current librarians
        $previousLibrarians = $remainingStudents->random(5);
        foreach ($previousLibrarians as $student) {
            Librarian::factory()->expired()->create([
                'user_id' => $student->id,
                'created_by' => $admin->id,
            ]);
        }

        // Create academic paper sessions (reading history)
        $studentsWithSessions = $students->random(20);

        foreach ($studentsWithSessions as $student) {
            $randomAcademicPapers = $academicPapers->random(rand(1, 4));

            foreach ($randomAcademicPapers as $academicPaper) {
                $copy = $academicPaper->copies()->inRandomOrder()->first();
                if ($copy) {
                    BorrowTransaction::factory()->completed()->create([
                        'user_id' => $student->id,
                        'academic_paper_id' => $academicPaper->id,
                        'inventory_id' => $copy->id,
                    ]);
                }
            }
        }

        // Create some active academic paper sessions
        $activeReaders = $students->random(5);
        foreach ($activeReaders as $student) {
            // Find theses that have available copies
            $academicPapersWithAvailableCopies = $academicPapers->filter(function ($academicPaper) {
                return $academicPaper->copies()->where('status', 'Available')->exists();
            });

            if ($academicPapersWithAvailableCopies->isNotEmpty()) {
                $availableAcademicPaper = $academicPapersWithAvailableCopies->random();
                $availableCopy = $availableAcademicPaper->copies()->where('status', 'Available')->first();

                if ($availableCopy) {
                    BorrowTransaction::factory()->active()->create([
                        'user_id' => $student->id,
                        'academic_paper_id' => $availableAcademicPaper->id,
                        'inventory_id' => $availableCopy->id,
                    ]);

                    // Mark the copy as reserved
                    $availableCopy->update(['status' => 'Reserved']);
                }
            }
        }

        // Create library entrance/exit sessions
        $studentsWithLibrarySessions = $students->random(25);
        foreach ($studentsWithLibrarySessions as $student) {
            // Create 2-5 library visits per student
            $visitCount = rand(2, 5);
            for ($i = 0; $i < $visitCount; $i++) {
                // Use existing librarians for scanned_by
                $randomLibrarian = collect([$librarianStudents, $previousLibrarians])->flatten()->random();
                $librarianRecord = Librarian::where('user_id', $randomLibrarian->id)->first();

                Attendance::factory()->completed()->create([
                    'user_id' => $student->id,
                    'scanned_by' => $librarianRecord ? $librarianRecord->id : null,
                ]);
            }
        }

        // Create some users currently in the library
        $currentlyInLibrary = $students->random(8);
        foreach ($currentlyInLibrary as $student) {
            // Use current active librarians for scanning
            $activeLibrarian = Librarian::where('user_id', $librarianStudents->random()->id)->first();

            Attendance::factory()->active()->create([
                'user_id' => $student->id,
                'scanned_by' => $activeLibrarian ? $activeLibrarian->id : null,
            ]);
        }

        $rulesHeaders = [
            ['order' => 1, 'roman' => 'I', 'title' => 'General Provisions'],
            ['order' => 2, 'roman' => 'II', 'title' => 'Library Hours'],
            ['order' => 3, 'roman' => 'III', 'title' => 'Library Users'],
            ['order' => 4, 'roman' => 'IV', 'title' => 'Borrowing and Returning'],
        ];

        foreach ($rulesHeaders as $rulesHeader) {
            $header = RuleHeader::create([
                'title' => $rulesHeader['roman'] . '. ' . $rulesHeader['title'],
                'order' => $rulesHeader['order'],
            ]);

            // Create 3-5 rules for each header
            for ($i = 1; $i <= rand(3, 5); $i++) {
                RuleRegulation::factory()->create([
                    'rule_header_id' => $header->id,
                    'order' => $i,
                ]);
            }
        }

        $this->command->info('PLV eLib database seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 52 users (2 Admin, 50 students)');
        $this->command->info('- 30 academic papers');
        $this->command->info('- 10 violation types');
        $this->command->info('- 3 active librarians on duty');
        $this->command->info('- 5 active borrowing transactions');
        $this->command->info('- 8 students currently in library');

        $this->command->info('- Sample violations, credit scores, and session history');
    }
}
