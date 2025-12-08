<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the scanned_by_name accessor in the Attendance model.
 * This verifies that when an admin (without librarian duty) scans a QR code,
 * the scanned_by_admin_id is properly used to display their name.
 */
class AdminScannedByTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that scanned_by_name accessor returns the admin's name when scanned_by_admin_id is set.
     */
    public function test_scanned_by_name_accessor_returns_correct_name(): void
    {
        // Get or create admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Admin role for testing']
        );

        // Create an admin user with admin role
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role_id' => $adminRole->id,
        ]);

        // Create a student
        $student = User::factory()->create();

        // Get or create a role for attendance
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student role for testing']
        );

        // Create attendance with scanned_by_admin_id set
        $attendance = Attendance::create([
            'user_id' => $student->id,
            'role_id' => $role->id,
            'status' => 'active',
            'time_in' => now(),
            'scanned_by_admin_id' => $admin->id,
        ]);

        // Load the relationship
        $attendance->load('scannedByAdmin');

        // Assert the accessor returns the admin's full name
        $this->assertEquals('Admin User', $attendance->scanned_by_name);
    }

    /**
     * Test that scanned_by_name returns 'Super Admin' when a super admin scans.
     */
    public function test_scanned_by_name_returns_super_admin_for_super_admin_scanner(): void
    {
        // Get or create super_admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin', 'description' => 'Super Admin role for testing']
        );

        // Create a super admin user
        $superAdmin = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role_id' => $superAdminRole->id,
        ]);

        // Create a student
        $student = User::factory()->create();

        // Get or create a role for attendance
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student role for testing']
        );

        // Create attendance with scanned_by_admin_id set to super admin
        $attendance = Attendance::create([
            'user_id' => $student->id,
            'role_id' => $role->id,
            'status' => 'active',
            'time_in' => now(),
            'scanned_by_admin_id' => $superAdmin->id,
        ]);

        // Load the relationship
        $attendance->load('scannedByAdmin');

        // Assert the accessor returns "Super Admin" instead of the name
        $this->assertEquals('Super Admin', $attendance->scanned_by_name);
    }

    /**
     * Test that scanned_by_name returns 'N/A' when both scanned_by fields are null.
     */
    public function test_scanned_by_name_returns_na_when_both_fields_are_null(): void
    {
        $student = User::factory()->create();
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student role for testing']
        );

        $attendance = Attendance::create([
            'user_id' => $student->id,
            'role_id' => $role->id,
            'status' => 'active',
            'time_in' => now(),
            // Neither scanned_by nor scanned_by_admin_id set
        ]);

        $this->assertEquals('N/A', $attendance->scanned_by_name);
    }

    /**
     * Test that scanned_by_name prioritizes librarian over admin when both are set.
     */
    public function test_scanned_by_name_prioritizes_librarian_over_admin(): void
    {
        // Create a librarian user
        $librarianUser = User::factory()->create([
            'first_name' => 'Librarian',
            'last_name' => 'Staff',
        ]);

        // Create a librarian record
        $librarian = Librarian::create([
            'user_id' => $librarianUser->id,
            'duty_start' => now()->subHour(),
            'duty_end' => now()->addHours(2),
        ]);

        // Create an admin user
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);

        $student = User::factory()->create();
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student role for testing']
        );

        // Create attendance with BOTH scanned_by and scanned_by_admin_id set
        $attendance = Attendance::create([
            'user_id' => $student->id,
            'role_id' => $role->id,
            'status' => 'active',
            'time_in' => now(),
            'scanned_by' => $librarian->id,
            'scanned_by_admin_id' => $admin->id,
        ]);

        // Load relationships
        $attendance->load(['scannedByLibrarian.user', 'scannedByAdmin']);

        // Librarian should take priority
        $this->assertEquals('Librarian Staff', $attendance->scanned_by_name);
    }
}
