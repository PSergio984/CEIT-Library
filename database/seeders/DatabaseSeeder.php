<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Thesis;
use App\Models\Violation;
use App\Models\UserViolation;
use App\Models\CreditScore;
use App\Models\ThesisSession;
use App\Models\LibrarySession;
use App\Models\Librarian;
use App\Models\ThesisCopy;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin users
        $admin = User::factory()->create([
            'name' => 'PLV Admin',
            'email' => 'admin@plv.edu.ph',
        ]);

        $libraryManager = User::factory()->create([
            'name' => 'Library Manager',
            'email' => 'library.manager@plv.edu.ph',
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

        // Create theses
        $theses = Thesis::factory(30)->create();

        // Create copies for each thesis
        $theses->each(function ($thesis) {
            // Random number of copies per thesis (1-4 copies)
            $copyCount = fake()->numberBetween(1, 4);

            for ($i = 1; $i <= $copyCount; $i++) {
                ThesisCopy::factory()->create([
                    'thesis_id' => $thesis->id,
                    'copy_number' => $i,
                    'status' => fake()->randomElement(['Available', 'Reserved', 'Unavailable']),
                ]);
            }
        });

        // Ensure we have some available copies for testing
        $availableTheses = $theses->take(10);
        $availableTheses->each(function ($thesis) {
            // Make sure at least one copy is available
            $firstCopy = $thesis->copies()->first();
            if ($firstCopy) {
                $firstCopy->update(['status' => 'Available']);
            }
        });

        // Create credit scores for all users (including admin)
        $allUsers = User::all();
        foreach ($allUsers as $user) {
            CreditScore::factory()->create(['user_id' => $user->id]);
        }

        // Create some user violations for random students
        $violations = Violation::all();
        $someStudents = $students->random(15); // 15 students with violations

        foreach ($someStudents as $student) {
            // Each student gets 1-3 violations
            $violationCount = rand(1, 3);
            $randomViolations = $violations->random($violationCount);

            foreach ($randomViolations as $violation) {
                UserViolation::factory()->create([
                    'user_id' => $student->id,
                    'violation_id' => $violation->id,
                ]);
            }

            // Update credit score based on violations
            $creditScore = CreditScore::where('user_id', $student->id)->first();
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

        // Create thesis sessions (reading history)
        $studentsWithSessions = $students->random(20);

        foreach ($studentsWithSessions as $student) {
            $randomTheses = $theses->random(rand(1, 4));

            foreach ($randomTheses as $thesis) {
                ThesisSession::factory()->completed()->create([
                    'user_id' => $student->id,
                    'thesis_id' => $thesis->id,
                ]);
            }
        }

        // Create some active thesis sessions
        $activeReaders = $students->random(5);
        foreach ($activeReaders as $student) {
            // Find theses that have available copies
            $thesesWithAvailableCopies = $theses->filter(function ($thesis) {
                return $thesis->copies()->where('status', 'Available')->exists();
            });

            if ($thesesWithAvailableCopies->isNotEmpty()) {
                $availableThesis = $thesesWithAvailableCopies->random();
                $availableCopy = $availableThesis->copies()->where('status', 'Available')->first();

                ThesisSession::factory()->active()->create([
                    'user_id' => $student->id,
                    'thesis_id' => $availableThesis->id,
                ]);

                // Mark the copy as reserved
                if ($availableCopy) {
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

                LibrarySession::factory()->completed()->create([
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

            LibrarySession::factory()->active()->create([
                'user_id' => $student->id,
                'scanned_by' => $activeLibrarian ? $activeLibrarian->id : null,
            ]);
        }

        $this->command->info('PLV eLib database seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 52 users (2 admin, 50 students)');
        $this->command->info('- 30 theses');
        $this->command->info('- 10 violation types');
        $this->command->info('- 3 active librarians on duty');
        $this->command->info('- 5 active thesis reading sessions');
        $this->command->info('- 8 students currently in library');
        $this->command->info('- Sample violations, credit scores, and session history');
    }
}
