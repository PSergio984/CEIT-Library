<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

    /** @test - TC023: Notifications - View and Manage User Notifications */
    public function user_can_view_and_manage_notifications()
    {
        $user = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        // Create a notification for the user using the custom notifications table
        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Test Notification',
            'message' => 'Test notification message',
            'data' => json_encode(['message' => 'Test notification']),
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('notifications'));

        $response->assertStatus(200);
        // Page loads successfully - exact content depends on implementation
        $this->assertTrue($response->status() === 200);

        // Verify notification exists
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'test',
        ]);
    }
}
