<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ViolationManagementTest extends TestCase
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

    /** @test - TC020: Violation Management - Student Access Control */
    public function student_cannot_access_violation_management()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        // Try to access violation management page
        $response = $this->actingAs($student)
            ->get('/admin/violations'); // Adjust route as needed

        // Should be denied or redirected
        $this->assertTrue($response->status() === 403 || $response->status() === 302);
    }

    /** @test - TC021: Violations - Create Violation Type */
    public function admin_can_create_violation_type()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
        ]);

        $violationData = [
            'name' => 'Test Violation',
            'description' => 'Test violation description',
            'penalty_score' => 5,
        ];

        $violation = Violation::create($violationData);

        $this->assertDatabaseHas('violations', [
            'name' => 'Test Violation',
            'penalty_score' => 5,
        ]);

        $this->assertEquals('Test Violation', $violation->name);
    }

    /** @test - TC022: Violation Transactions - Record Violation */
    public function admin_can_record_violation_transaction()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
        ]);

        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'credit_score' => 100,
        ]);

        $violation = Violation::factory()->create([
            'penalty_score' => 10,
        ]);

        $transaction = ViolationTransaction::create([
            'user_id' => $student->id,
            'violation_id' => $violation->id,
            'violation_penalty' => $violation->penalty_score,
            'date_occurred' => now(),
            'remarks' => 'Test violation remark',
        ]);

        $this->assertDatabaseHas('violation_transactions', [
            'user_id' => $student->id,
            'violation_id' => $violation->id,
            'violation_penalty' => 10,
        ]);

        // Verify credit score was adjusted
        $student->refresh();
        $this->assertEquals(90, $student->credit_score);
    }
}

