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
        // Get role IDs
        $superAdminRoleId = \App\Models\Role::where('name', 'super_admin')->value('id') ?? 3;
        $librarianRoleId = \App\Models\Role::where('name', 'librarian')->value('id') ?? 2;
        $studentRoleId = \App\Models\Role::where('name', 'student')->value('id') ?? 1;

        // Create the ONLY super_admin user
        $admin = User::factory()->create([
            'first_name' => 'Janrel',
            'last_name' => 'Motovlogs',
            'email' => 'admin@plv.edu.ph',
            'role_id' => $superAdminRoleId,
            'password' => bcrypt('Pwd@12345'),
        ]);

        // Create regular student users (including former librarian as student)
        $students = User::factory(50)->create();

        // Create a test librarian student (starts as student, can be promoted by super_admin)
        $testLibrarian = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Librarian',
            'email' => 'librarian@plv.edu.ph',
            'role_id' => $studentRoleId,
            'password' => bcrypt('Pwd@12345'),
        ]);

        $students->push($testLibrarian);

        // Create a specific student user
        $specificStudent = User::factory()->create([
            'first_name' => 'Sample',
            'last_name' => 'Student',
            'email' => 'student@plv.edu.ph',
            'role_id' => $studentRoleId,
            'password' => bcrypt('Pwd@12345'),
        ]);

        // Add the specific student to the students collection for randomization
        $students->push($specificStudent);

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

        // Create research advisers, technical advisers, and deans first
        $researchAdvisers = \App\Models\ResearchAdviser::factory(15)->create();
        $technicalAdvisers = \App\Models\TechnicalAdviser::factory(15)->create();
        $deans = \App\Models\Dean::factory(5)->create();

        // Create academic papers with adviser and dean relationships
        $academicPapers = AcademicPaper::factory(30)->create([
            'research_adviser_id' => fn() => $researchAdvisers->random()->id,
            'technical_adviser_id' => fn() => $technicalAdvisers->random()->id,
            'dean_id' => fn() => $deans->random()->id,
        ]);

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
                    'status' => fake()->randomElement(['Available', 'Unavailable']),
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

        // Note: ScoreIncrements are now individual reward records, not total scores
        // The user's credit_score column in the users table tracks the total
        // Only create ScoreIncrements for actual reward events

        // Create some user violations for random students
        $violations = Violation::all();
        $someStudents = $students->random(15); // 15 students with violations

        foreach ($someStudents as $violationStudent) {
            // Each student gets 1-3 violations
            $violationCount = rand(1, 3);
            $randomViolations = $violations->random($violationCount);

            foreach ($randomViolations as $violation) {
                ViolationTransaction::factory()->create([
                    'user_id' => $violationStudent->id,
                    'violation_id' => $violation->id,
                ]);
            }
            // Note: Credit scores are now automatically updated via model events
        }

        // Create at least 5 borrow transactions for each status for the specific student
        // Use only allowed enum values for status: ['started', 'completed', 'overdue']
        $statuses = [
            'completed',    // completed transaction
            'started',      // active/ongoing transaction
            'overdue',      // overduetransaction
        ];

        // Eager load copies to avoid N+1 queries
        $academicPapers->load('copies');
        $academicPapersWithCopies = $academicPapers->filter(function ($paper) {
            return $paper->copies->isNotEmpty();
        });

        foreach ($statuses as $status) {
            for ($i = 0; $i < 5; $i++) {
                $paper = $academicPapersWithCopies->random();
                $copy = $paper->copies->random();
                if ($copy) {
                    BorrowTransaction::factory()->state([
                        'user_id' => $specificStudent->id,
                        'academic_paper_id' => $paper->id,
                        'inventory_id' => $copy->id,
                        'status' => $status,
                    ])->create();
                }
            }
        }



        // Create librarian batches
        $today = \Carbon\Carbon::today();
        $currentYear = date('Y');
        $allLibrarianStudents = collect();

        // 1. Create 1 ACTIVE batch (on duty today) - exactly 5 students
        $this->command->info('Creating active batch for today...');
        $activeBatchStudents = $students->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($activeBatchStudents);

        foreach ($activeBatchStudents as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0001',
                'start_date' => $today,
                'end_date' => null,
                'status' => 'active',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Create 2 EXPIRED batches (dates in the past) - 5 students each
        $this->command->info('Creating 2 expired batches...');
        $remainingStudents = $students->diff($allLibrarianStudents);

        // Expired Batch 1 (ended 3 days ago)
        $expiredBatch1Students = $remainingStudents->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($expiredBatch1Students);
        $remainingStudents = $remainingStudents->diff($expiredBatch1Students);

        foreach ($expiredBatch1Students as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0002',
                'start_date' => $today->copy()->subDays(10),
                'end_date' => $today->copy()->subDays(3),
                'status' => 'expired',
                'created_by' => $admin->id,
                'created_at' => $today->copy()->subDays(10),
                'updated_at' => $today->copy()->subDays(3),
            ]);
        }

        // Expired Batch 2 (ended 1 week ago)
        $expiredBatch2Students = $remainingStudents->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($expiredBatch2Students);
        $remainingStudents = $remainingStudents->diff($expiredBatch2Students);

        foreach ($expiredBatch2Students as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0003',
                'start_date' => $today->copy()->subDays(20),
                'end_date' => $today->copy()->subDays(7),
                'status' => 'expired',
                'created_by' => $admin->id,
                'created_at' => $today->copy()->subDays(20),
                'updated_at' => $today->copy()->subDays(7),
            ]);
        }

        // 3. Create 3 INACTIVE batches (future dates) - 5 students each
        $this->command->info('Creating 3 inactive batches...');

        // Inactive Batch 1 (starts in 2 days)
        $inactiveBatch1Students = $remainingStudents->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($inactiveBatch1Students);
        $remainingStudents = $remainingStudents->diff($inactiveBatch1Students);

        foreach ($inactiveBatch1Students as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0004',
                'start_date' => $today->copy()->addDays(2),
                'end_date' => null,
                'status' => 'inactive',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Inactive Batch 2 (starts in 5 days)
        $inactiveBatch2Students = $remainingStudents->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($inactiveBatch2Students);
        $remainingStudents = $remainingStudents->diff($inactiveBatch2Students);

        foreach ($inactiveBatch2Students as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0005',
                'start_date' => $today->copy()->addDays(5),
                'end_date' => null,
                'status' => 'inactive',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Inactive Batch 3 (starts in 1 week)
        $inactiveBatch3Students = $remainingStudents->random(5);
        $allLibrarianStudents = $allLibrarianStudents->merge($inactiveBatch3Students);

        foreach ($inactiveBatch3Students as $student) {
            Librarian::create([
                'user_id' => $student->id,
                'batch_no' => $currentYear . '0006',
                'start_date' => $today->copy()->addDays(7),
                'end_date' => null,
                'status' => 'inactive',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Store active batch students for use in attendance records
        $librarianStudents = $activeBatchStudents;
        $previousLibrarians = $expiredBatch1Students->merge($expiredBatch2Students);

        // Create academic paper sessions (reading history)
        $studentsWithSessions = $students->random(20);

        foreach ($studentsWithSessions as $sessionStudent) {
            $randomAcademicPapers = $academicPapers->random(rand(1, 4));

            foreach ($randomAcademicPapers as $academicPaper) {
                $copy = $academicPaper->copies()->inRandomOrder()->first();
                if ($copy) {
                    BorrowTransaction::factory()->completed()->create([
                        'user_id' => $sessionStudent->id,
                        'academic_paper_id' => $academicPaper->id,
                        'inventory_id' => $copy->id,
                    ]);
                }
            }
        }

        // Create some active academic paper sessions
        $activeReaders = $students->random(5);
        foreach ($activeReaders as $activeReader) {
            // Find theses that have available copies
            $academicPapersWithAvailableCopies = $academicPapers->filter(function ($academicPaper) {
                return $academicPaper->copies()->where('status', 'Available')->exists();
            });

            if ($academicPapersWithAvailableCopies->isNotEmpty()) {
                $availableAcademicPaper = $academicPapersWithAvailableCopies->random();
                $availableCopy = $availableAcademicPaper->copies()->where('status', 'Available')->first();

                if ($availableCopy) {
                    BorrowTransaction::factory()->active()->create([
                        'user_id' => $activeReader->id,
                        'academic_paper_id' => $availableAcademicPaper->id,
                        'inventory_id' => $availableCopy->id,
                    ]);

                    // Mark the copy as reserved
                    $availableCopy->update(['status' => 'unavailable']);
                }
            }
        }

        // Create library entrance/exit sessions
        $studentsWithLibrarySessions = $students->random(25);
        foreach ($studentsWithLibrarySessions as $libraryVisitorStudent) {
            // Create 2-5 library visits per student
            $visitCount = rand(2, 5);
            for ($i = 0; $i < $visitCount; $i++) {
                // Use existing librarians for scanned_by
                $randomLibrarian = collect([$librarianStudents, $previousLibrarians])->flatten()->random();
                $librarianRecord = Librarian::where('user_id', $randomLibrarian->id)->first();

                Attendance::factory()->completed()->create([
                    'user_id' => $libraryVisitorStudent->id,
                    'scanned_by' => $librarianRecord ? $librarianRecord->id : null,
                ]);
            }
        }

        // Ensure the specific student has at least 10 attendance records with varied dates and statuses
        // Note: $specificStudent was created earlier as student@plv.edu.ph
        $today = \Carbon\Carbon::today();
        $dates = [
            $today->copy()->subDays(4),
            $today->copy()->subDays(3),
            $today->copy()->subDays(2),
            $today->copy()->subDay(),
            $today->copy(),
            $today->copy()->subDays(5),
            $today->copy()->subDays(6),
            $today->copy()->subDays(7),
            $today->copy()->subDays(8),
            $today->copy()->subDays(9),
        ];

        $attendanceStatuses = ['completed', 'active', 'completed', 'active', 'completed', 'completed', 'active', 'completed', 'active', 'completed'];

        for ($i = 0; $i < 10; $i++) {
            $date = $dates[$i];
            $status = $attendanceStatuses[$i] ?? 'completed';

            $libr = Librarian::inRandomOrder()->first();
            $scannedBy = $libr ? $libr->id : null;
            $createdHour = rand(8, 17);
            $createdMinute = rand(0, 59);
            $updatedHour = $createdHour + ($status === 'completed' ? 2 : 0);
            $updatedMinute = $createdMinute;

            if ($status === 'completed') {
                Attendance::factory()->completed()->create([
                    'user_id' => $specificStudent->id,
                    'scanned_by' => $scannedBy,
                    'created_at' => $date->copy()->setTime($createdHour, $createdMinute, 0),
                    'updated_at' => $date->copy()->setTime($updatedHour, $updatedMinute, 0),
                ]);
            } else {
                Attendance::factory()->active()->create([
                    'user_id' => $specificStudent->id,
                    'scanned_by' => $scannedBy,
                    'created_at' => $date->copy()->setTime($createdHour, $createdMinute, 0),
                    'updated_at' => $date->copy()->setTime($updatedHour, $updatedMinute, 0),
                ]);
            }
        }

        // Create some users currently in the library
        $currentlyInLibrary = $students->random(8);
        foreach ($currentlyInLibrary as $currentLibraryUser) {
            // Use current active librarians for scanning
            $activeLibrarian = Librarian::where('user_id', $librarianStudents->random()->id)->first();

            Attendance::factory()->active()->create([
                'user_id' => $currentLibraryUser->id,
                'scanned_by' => $activeLibrarian ? $activeLibrarian->id : null,
            ]);
        }

        // Add positive score increment history for the specific student (student@plv.edu.ph)
        // These represent rewards/bonuses that increase their credit score
        $rewardDates = [
            $today->copy()->subDays(10),
            $today->copy()->subDays(7),
            $today->copy()->subDays(5),
            $today->copy()->subDays(3),
            $today->copy()->subDay(),
        ];

        $scoreIncrement1 = new ScoreIncrement([
            'user_id' => $specificStudent->id,
            'name' => 'Perfect Attendance (Week)',
            'description' => 'Attended library every day this week',
            'score_value' => 15,
        ]);
        $scoreIncrement1->forceFill([
            'created_at' => $rewardDates[0]->setTime(17, 30, 0),
            'updated_at' => $rewardDates[0]->setTime(17, 30, 0),
        ]);
        $scoreIncrement1->save();

        $scoreIncrement2 = new ScoreIncrement([
            'user_id' => $specificStudent->id,
            'name' => 'Library Event Participation',
            'description' => 'Participated in library orientation program',
            'score_value' => 12,
        ]);
        $scoreIncrement2->forceFill([
            'created_at' => $rewardDates[1]->setTime(13, 0, 0),
            'updated_at' => $rewardDates[1]->setTime(13, 0, 0),
        ]);
        $scoreIncrement2->save();

        $scoreIncrement3 = new ScoreIncrement([
            'user_id' => $specificStudent->id,
            'name' => 'Helping Other Students',
            'description' => 'Assisted fellow student in finding research materials',
            'score_value' => 8,
        ]);
        $scoreIncrement3->forceFill([
            'created_at' => $rewardDates[2]->setTime(11, 20, 0),
            'updated_at' => $rewardDates[2]->setTime(11, 20, 0),
        ]);
        $scoreIncrement3->save();

        $scoreIncrement4 = new ScoreIncrement([
            'user_id' => $specificStudent->id,
            'name' => 'Early Bird Bonus',
            'description' => 'First student to arrive at library opening time',
            'score_value' => 5,
        ]);
        $scoreIncrement4->forceFill([
            'created_at' => $rewardDates[3]->setTime(8, 0, 0),
            'updated_at' => $rewardDates[3]->setTime(8, 0, 0),
        ]);
        $scoreIncrement4->save();

        $scoreIncrement5 = new ScoreIncrement([
            'user_id' => $specificStudent->id,
            'name' => 'Book Care Excellence',
            'description' => 'Maintained borrowed materials in excellent condition',
            'score_value' => 10,
        ]);
        $scoreIncrement5->forceFill([
            'created_at' => $rewardDates[4]->setTime(15, 45, 0),
            'updated_at' => $rewardDates[4]->setTime(15, 45, 0),
        ]);
        $scoreIncrement5->save();

        // Add sample violations for the specific student (student@plv.edu.ph)
        // These will show negative points in the credit score history
        $violationDates = [
            $today->copy()->subDays(12),
            $today->copy()->subDays(8),
            $today->copy()->subDays(4),
            $today->copy()->subDays(2),
        ];

        // Late Return violation (Minor severity, -5 points)
        $lateReturnViolation = Violation::where('name', 'Late Return of Books')->first();
        if ($lateReturnViolation) {
            ViolationTransaction::create([
                'user_id' => $specificStudent->id,
                'violation_id' => $lateReturnViolation->id,
                'date_occurred' => $violationDates[0],
                'severity' => 'Minor',
                'remarks' => 'Returned thesis 2 days past due date',
            ]);
        }

        // Loud Talking violation (Minor severity, -3 points)
        $loudTalkingViolation = Violation::where('name', 'Loud Talking in Library')->first();
        if ($loudTalkingViolation) {
            ViolationTransaction::create([
                'user_id' => $specificStudent->id,
                'violation_id' => $loudTalkingViolation->id,
                'date_occurred' => $violationDates[1],
                'severity' => 'Minor',
                'remarks' => 'Talking loudly in the reading area',
            ]);
        }

        // Eating in Library violation (Minor severity, -4 points)
        $eatingViolation = Violation::where('name', 'Eating in Library')->first();
        if ($eatingViolation) {
            ViolationTransaction::create([
                'user_id' => $specificStudent->id,
                'violation_id' => $eatingViolation->id,
                'date_occurred' => $violationDates[2],
                'severity' => 'Minor',
                'remarks' => 'Eating snacks in the study area',
            ]);
        }

        // Mobile Phone violation (Minor severity, -3 points)
        $mobilePhoneViolation = Violation::where('name', 'Using Mobile Phone Loudly')->first();
        if ($mobilePhoneViolation) {
            ViolationTransaction::create([
                'user_id' => $specificStudent->id,
                'violation_id' => $mobilePhoneViolation->id,
                'date_occurred' => $violationDates[3],
                'severity' => 'Minor',
                'remarks' => 'Received a call without leaving the study area',
            ]);
        }

        $rulesHeaders = [
            ['title' => 'I.General Information', 'order' => 1],
            ['title' => 'II.Duties and Responsibilities', 'order' => 2],
            ['title' => 'III.Study Room Rules and Regulations', 'order' => 3],
        ];

        foreach ($rulesHeaders as $headerData) {
            $header = RuleHeader::create($headerData);
            // Create 3-5 rules for each header
            for ($i = 1; $i <= rand(3, 5); $i++) {
                RuleRegulation::factory()->create([
                    'rule_header_id' => $header->id,
                ]);
            }
        }

        $this->command->info('PLV eLib database seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 52 users (1 Super Admin, 51 students)');
        $this->command->info('- ONLY super_admin: Janrel Motovlogs (admin@plv.edu.ph)');
        $this->command->info('- 10 violation types');
        $this->command->info('- Librarian Batches:');
        $this->command->info('  * 1 ACTIVE batch (5 students on duty today)');
        $this->command->info('  * 2 EXPIRED batches (5 students each, dates in the past)');
        $this->command->info('  * 3 INACTIVE batches (5 students each, future start dates)');
        $this->command->info('- 5 active borrowing transactions');
        $this->command->info('- 8 students currently in library');
        $this->command->info('- 3 Main Headers for Rules and Regulations with 3-5 rules each');
    }
}
