<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordValidationTest extends TestCase
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

    /** @test - TC033: Rate Limiting - Password Reset */
    #[Test]
    public function password_reset_requests_are_rate_limited()
    {
        // Clear any existing rate limiters
        RateLimiter::clear('password.reset');

        $user = User::factory()->create();

        // Make 3 requests rapidly
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post(route('password.email'), [
                'email' => $user->email,
            ]);
            $response->assertStatus(302); // Redirect after submission
        }

        // 4th request should be rate limited
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        // Should receive rate limit error
        $response->assertStatus(429); // Too Many Requests
    }
}
