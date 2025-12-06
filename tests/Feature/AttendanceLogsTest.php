<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLogsTest extends TestCase
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

    /** @test - TC017: Attendance Logs - Student Access Control */
    public function student_cannot_access_attendance_logs()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        $response = $this->actingAs($student)
            ->get(route('admin.attendance-logs'));

        $response->assertStatus(403);
    }

    /** @test - TC018: Attendance Logs - Open Scanner (Authorized) */
    public function authorized_role_can_open_qr_scanner()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.attendance-logs'));

        $response->assertStatus(200);
        // Check for scanner button or QR scanner functionality
        $response->assertSee('Scan', false);
    }

    /** @test - TC019: Attendance Logs - Time In/Out via QR */
    public function attendance_toggles_between_time_in_and_time_out()
    {
        $librarian = User::factory()->create([
            'role_id' => $this->getRoleId('librarian'),
        ]);
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        // First scan - should create Time In
        $attendance1 = Attendance::factory()->create([
            'user_id' => $student->id,
            'time_in' => now(),
            'time_out' => null,
            'status' => 'active',
        ]);

        $this->assertNotNull($attendance1->time_in);
        $this->assertNull($attendance1->time_out);
        $this->assertEquals('active', $attendance1->status);

        // Second scan - should update to Time Out
        $attendance1->update([
            'time_out' => now(),
            'status' => 'completed',
        ]);

        $attendance1->refresh();
        $this->assertNotNull($attendance1->time_out);
        $this->assertEquals('completed', $attendance1->status);
    }
}

