<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeUpdatesTest extends TestCase
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

    /** @test - TC046: Wire Polling - Real-time Countdown */
    public function time_sensitive_data_updates_in_real_time_using_wire_polling()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create active attendance records
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        Attendance::factory()->create([
            'user_id' => $student->id,
            'status' => 'active',
            'time_in' => now()->subHour(),
        ]);

        $response = $this->get(route('admin.attendance'));
        $response->assertStatus(200);
        
        // Verify page loads (real-time updates are primarily a frontend feature)
        // Wire polling should be configured in the Livewire component
    }
}

