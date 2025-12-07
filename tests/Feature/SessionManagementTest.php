<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementTest extends TestCase
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

    /** @test - TC074: Session Timeout - Auto Logout */
    public function user_is_logged_out_after_session_timeout()
    {
        $user = User::factory()->create();

        // Login
        $this->actingAs($user);
        $this->get(route('dashboard'))->assertStatus(200);

        // Simulate session expiration by clearing session
        $this->app['session']->flush();

        // Attempt to access protected page
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }
}
