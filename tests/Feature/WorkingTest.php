<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Librarian;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\TestHelper;

class WorkingTest extends TestCase
{
    use TestHelper;

    public function test_basic_models_work()
    {
        // Test User creation
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@plv.edu.ph', // Use valid PLV email
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@plv.edu.ph',
        ]);

        // Test AcademicPaper creation
        $paper = AcademicPaper::factory()->create([
            'title' => 'Test Paper',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Test Paper',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        // Test Inventory creation
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
        ]);

        $this->assertDatabaseHas('inventories', [
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
        ]);

        // Test BorrowTransaction creation
        $transaction = BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => Carbon::now(),
            'status' => 'started',
            'expires_at' => Carbon::now()->addDays(14),
            'session_token' => $this->generateSessionToken(),
        ]);

        $this->assertDatabaseHas('borrow_transactions', [
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'status' => 'started',
        ]);

        // Test Attendance creation
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'time_in' => Carbon::now(),
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Test Librarian creation
        $librarian = Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250001', // String, not integer
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $this->assertDatabaseHas('librarians', [
            'user_id' => $user->id,
            'batch_no' => '20250001',
            'status' => 'active',
        ]);

        // Test Violation creation
        $violation = Violation::factory()->create([
            'name' => 'Late Return',
            'penalty_score' => 10,
        ]);

        $this->assertDatabaseHas('violations', [
            'name' => 'Late Return',
            'penalty_score' => 10,
        ]);

        // Test ViolationTransaction creation with correct schema
        $violationTransaction = ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation->id,
            'remarks' => 'Late return violation', // Use remarks instead of penalty
            'date_occurred' => Carbon::now(),
        ]);

        $this->assertDatabaseHas('violation_transactions', [
            'user_id' => $user->id,
            'violation_id' => $violation->id,
        ]);
    }

    public function test_relationships_work()
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
        ]);

        // Test user relationships
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->borrowTransactions()
        );

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->librarySessions()
        );

        if (method_exists($paper, 'copies')) {
            $this->assertInstanceOf(
                \Illuminate\Database\Eloquent\Relations\HasMany::class,
                $paper->copies()
            );
        }
    }

    public function test_no_fulltext_errors()
    {
        // This test verifies that we can create academic papers without fulltext index errors
        $paper = AcademicPaper::factory()->create([
            'title' => 'A Paper About Fulltext Search',
            'research_project_adviser' => 'Dr. Fulltext Expert',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'A Paper About Fulltext Search',
            'research_project_adviser' => 'Dr. Fulltext Expert',
        ]);
    }
}
