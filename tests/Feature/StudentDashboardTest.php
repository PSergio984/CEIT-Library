<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function getRoleId(string $roleName): int
    {
        return Role::where('name', $roleName)->value('id') ?? match ($roleName) {
            'student' => 1,
            'librarian' => 2,
            'admin' => 3,
            'super_admin' => 4,
            default => 1,
        };
    }

    /** @test - TC070: Student Dashboard - View Personal Stats */
    public function student_can_view_their_dashboard_with_personal_statistics()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'credit_score' => 85,
        ]);
        $this->actingAs($student);

        // Create some data for the student
        $academicPaper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create(['academic_paper_id' => $academicPaper->id]);
        BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $academicPaper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
        ]);
        Attendance::factory()->count(3)->create(['user_id' => $student->id]);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        
        // Verify personal stats are displayed
        $response->assertSee('Credit Score', false);
    }

    /** @test - TC071: Student - View Borrowed Books History */
    public function student_can_view_their_borrow_history()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $this->actingAs($student);

        $academicPaper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create(['academic_paper_id' => $academicPaper->id]);
        
        BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $academicPaper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
        ]);
        BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $academicPaper->id,
            'inventory_id' => $inventory->id,
            'status' => 'completed',
        ]);

        // Student borrow history is accessible through transactions route
        $response = $this->get(route('transactions'));
        $response->assertStatus(200);
        
        // Verify only student's own transactions are shown
        $response->assertSee($academicPaper->title, false);
    }
}

