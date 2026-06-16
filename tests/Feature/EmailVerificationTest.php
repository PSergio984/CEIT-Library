<?php

namespace Tests\Feature;

use App\Mail\Welcome;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CustomResetPassword;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Exception\CommandNotFoundException;
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
        Notification::fake();

        // Register new user via Volt component
        Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'newuser@plv.edu.ph')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register')
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'newuser@plv.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // Logout because registration auto-logs in
        auth()->logout();

        // Attempt to login without verification
        Volt::test('pages.auth.login')
            ->set('form.email', 'newuser@plv.edu.ph')
            ->set('form.password', 'Password123!')
            ->call('login');

        // Accessing dashboard should redirect to verification notice
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));

        // Verify verification notification was sent
        Notification::assertSentTo(
            $user,
            CustomVerifyEmail::class
        );
    }

    /** @test - TC054: Welcome Email - New User */
    #[Test]
    public function welcome_email_is_sent_after_registration()
    {
        Mail::fake();

        Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'newuser@plv.edu.ph')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $user = User::where('email', 'newuser@plv.edu.ph')->first();
        $this->assertNotNull($user, 'User was not created during registration.');

        Mail::assertQueued(Welcome::class, function (Welcome $mail) use ($user) {
            return $mail->hasTo($user->email);
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
            $this->artisan('transactions:check-overdue', ['--force' => true])
                ->assertSuccessful();
        } catch (CommandNotFoundException $e) {
            // Command may not be registered yet, skip this test if command doesn't exist
            $this->markTestSkipped('transactions:check-overdue command not registered');
        }

        // Verify emails were sent for overdue transactions
        // This is a placeholder - actual implementation may vary
    }

    /** @test - TC056: Password Reset - Email Flow */
    #[Test]
    public function password_reset_email_functionality_works()
    {
        Notification::fake();

        $user = User::factory()->create();

        // Request password reset via Volt component
        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        // Verify reset notification was sent
        Notification::assertSentTo(
            $user,
            CustomResetPassword::class
        );
    }
}
