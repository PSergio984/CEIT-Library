<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\ActiveUsersTab;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViolationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'Student', 'display_name' => 'Student', 'description' => 'Student role']);
        Role::create(['name' => 'Admin', 'display_name' => 'Admin', 'description' => 'Admin role']);
    }

    public function test_notification_is_created_when_admin_records_violation(): void
    {
        // Create an admin user
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        // Create a student user
        $studentRole = Role::where('name', 'Student')->first();
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
        ]);

        // Create a violation
        $violation = Violation::create([
            'name' => 'Test Violation',
            'description' => 'This is a test violation',
            'penalty_score' => 10,
        ]);

        // Create an active attendance for the student
        Attendance::create([
            'user_id' => $student->id,
            'time_in' => now()->subHour(),
            'time_out' => null,
            'status' => 'active',
            'duration_minutes' => 0,
        ]);

        // Assert no notifications exist initially
        $this->assertEquals(0, Notification::count());

        // Act as admin and record violation
        Livewire::actingAs($admin)
            ->test(ActiveUsersTab::class)
            ->set('selectedUserForViolation', $student->id)
            ->set('selectedViolationId', $violation->id)
            ->set('violationRemarks', 'Test remark')
            ->call('recordViolation');

        // Assert notification was created
        $this->assertEquals(1, Notification::count());

        $notification = Notification::first();
        $this->assertEquals($student->id, $notification->user_id);
        $this->assertEquals('violation', $notification->type);
        $this->assertEquals('Violation Recorded', $notification->title);
        $this->assertStringContainsString($violation->name, $notification->message);
        $this->assertStringContainsString('-'.$violation->penalty_score, $notification->message);
        $this->assertFalse($notification->is_read);

        // Assert notification data
        $this->assertEquals($violation->id, $notification->data['violation_id']);
        $this->assertEquals($violation->name, $notification->data['violation_name']);
        $this->assertEquals($violation->penalty_score, $notification->data['penalty_score']);
        $this->assertEquals($admin->id, $notification->data['recorded_by']);
    }

    public function test_notification_is_created_when_admin_declares_forgot_timeout(): void
    {
        // Create an admin user
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        // Create a student user
        $studentRole = Role::where('name', 'Student')->first();
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
        ]);

        // Create an active attendance for the student
        $attendance = Attendance::create([
            'user_id' => $student->id,
            'time_in' => now()->subHours(2),
            'time_out' => null,
            'status' => 'active',
            'duration_minutes' => 0,
        ]);

        // Assert no notifications exist initially
        $this->assertEquals(0, Notification::count());

        // Act as admin and declare forgot timeout
        Livewire::actingAs($admin)
            ->test(ActiveUsersTab::class)
            ->call('declareForgotTimeout', $attendance->id);

        // Assert notification was created
        $this->assertEquals(1, Notification::count());

        $notification = Notification::first();
        $this->assertEquals($student->id, $notification->user_id);
        $this->assertEquals('violation', $notification->type);
        $this->assertEquals('Violation Recorded: Forgot to Time Out', $notification->title);
        $this->assertStringContainsString('forgot to time out', $notification->message);
        $this->assertFalse($notification->is_read);

        // Assert notification data
        $this->assertEquals('Forgot to time out', $notification->data['violation_name']);
        $this->assertEquals($admin->id, $notification->data['recorded_by']);
    }
}
