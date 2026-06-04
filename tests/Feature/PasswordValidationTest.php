<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
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
        config(['auth.passwords.users.throttle' => 0]);
        
        $email = 'test@plv.edu.ph';
        $user = User::factory()->create(['email' => $email]);
        
        // Clear any existing rate limiters
        $key = 'forgot-password|' . strtolower($email) . '|' . request()->ip();
        RateLimiter::clear($key);

        $component = Volt::test('pages.auth.forgot-password');

        // Make 3 requests rapidly (limit is 3 in config/throttle.php)
        for ($i = 0; $i < 3; $i++) {
            $component->set('email', $email)
                ->call('sendPasswordResetLink')
                ->assertHasNoErrors('email');
        }

        // 4th request should be rate limited
        $component->set('email', $email)
            ->call('sendPasswordResetLink')
            ->assertHasErrors(['email']);
            
        $errorMessage = $component->errors()->get('email')[0];
        $this->assertStringContainsString('Too many password reset attempts', $errorMessage);
    }
}
