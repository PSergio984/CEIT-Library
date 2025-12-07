<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\ActiveUsersTab;
use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
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

    protected function seedRequiredData(): void
    {
        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student', 'description' => 'Student']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian', 'description' => 'Librarian']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'description' => 'Admin']);
        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);
        Violation::firstOrCreate(['name' => 'Forgot to time out'], ['description' => 'Marked by admin as forgot to time out', 'penalty_score' => 5]);
    }

    /** @test - TC060: Attendance - Manual Declare Forgot Timeout */
    public function admin_can_declare_forgot_timeout_for_active_attendance()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $librarianUser = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);
        $librarian = Librarian::factory()->create([
            'user_id' => $librarianUser->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $student = User::factory()->create(['role_id' => $this->getRoleId('student'), 'credit_score' => 100]);

        // Create active attendance (forgot to time out) using the active() state
        $attendance = Attendance::factory()->active()->create([
            'user_id' => $student->id,
            'scanned_by' => $librarian->id,
        ]);

        // Verify attendance is active before the call
        $this->assertEquals('active', $attendance->status);
        $this->assertTrue($attendance->isActive());

        // Declare forgot timeout using the ActiveUsersTab component
        $component = Livewire::actingAs($admin)
            ->test(ActiveUsersTab::class)
            ->call('declareForgotTimeout', $attendance->id);

        // Check if there were any errors
        $component->assertHasNoErrors();

        // Refresh the attendance model to get the latest data
        $attendance->refresh();

        // Verify attendance is marked complete
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($attendance->time_out);

        // Verify violation was recorded (the violation name is "Forgot to time out")
        $violation = Violation::where('name', 'Forgot to time out')->first();
        $this->assertNotNull($violation);
        $this->assertDatabaseHas('violation_transactions', [
            'user_id' => $student->id,
            'violation_id' => $violation->id,
        ]);

        // Verify credit score was updated
        $student->refresh();
        $this->assertLessThan(100, $student->credit_score);
    }
}
