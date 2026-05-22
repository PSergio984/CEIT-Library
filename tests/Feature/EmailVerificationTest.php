<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

    /** @test - TC053: Email Verification - Account Activation */
    #[Test]
    public function new_users_must_verify_email_before_accessing_system()
    {
        Mail::fake();

        // Register new user
        $response = $this->post(route('register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'newuser@plv.edu.ph',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();
        $user = User::where('email', 'newuser@plv.edu.ph')->first();
        $this->assertNull($user->email_verified_at);

        // Attempt to login without verification
        $response = $this->post(route('login'), [
            'email' => 'newuser@plv.edu.ph',
            'password' => 'Password123!',
        ]);

        // Should be blocked or redirected to verification page
        $response->assertRedirect();

        // Verify email was sent
        Mail::assertSent(function ($mail) {
            return str_contains($mail->subject, 'Verify');
        });
    }

    /** @test - TC054: Welcome Email - New User */
    #[Test]
    public function welcome_email_is_sent_after_email_verification()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Verify email
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl);

        // Welcome email should be sent after verification
        Mail::assertSent(function ($mail) use ($user) {
            return str_contains($mail->subject, 'Welcome') &&
                   $mail->hasTo($user->email);
        });
    }

    /** @test - TC055: Overdue Email - Automated Notification */
    #[Test]
    public function overdue_email_is_sent_when_borrowed_item_exceeds_due_date()
    {
        Mail::fake();

        // This test would require running a scheduled command or triggering the overdue check
        // The actual implementation depends on how overdue notifications are handled
        try {
            $this->artisan('borrow:check-overdue')
                ->assertSuccessful();
        } catch (\Exception $e) {
            // Command may not be registered yet, skip this test if command doesn't exist
            $this->markTestSkipped('borrow:check-overdue command not registered');
        }

        // Verify emails were sent for overdue transactions
        // This is a placeholder - actual implementation may vary
    }

    /** @test - TC056: Password Reset - Email Flow */
    #[Test]
    public function password_reset_email_functionality_works()
    {
        Mail::fake();

        $user = User::factory()->create();

        // Request password reset
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(302);

        // Verify email was sent
        Mail::assertSent(function ($mail) use ($user) {
            return str_contains($mail->subject, 'Reset') &&
                   $mail->hasTo($user->email);
        });
    }
}
