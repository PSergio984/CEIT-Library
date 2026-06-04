<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test.user@plv.edu.ph',
        ]);

        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        $component->assertHasNoErrors();

        Notification::assertSentTo($user, CustomResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test.user2@plv.edu.ph',
        ]);

        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        $component->assertHasNoErrors();

        $resetToken = null;
        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;

            return true;
        });

        $this->assertNotNull($resetToken);

        $response = $this->get('/reset-password/'.$resetToken);

        $response
            ->assertSeeVolt('pages.auth.reset-password')
            ->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test.user3@plv.edu.ph',
            'password' => Hash::make('old-password'),
        ]);

        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        $component->assertHasNoErrors();

        $resetToken = null;
        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;

            return true;
        });

        $this->assertNotNull($resetToken);

        $component = Volt::test('pages.auth.reset-password', ['token' => $resetToken])
            ->set('email', $user->email)
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!');

        $component->call('resetPassword');

        $component
            ->assertRedirect('/login')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_password_reset_requires_valid_email(): void
    {
        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', 'invalid-email')
            ->call('sendPasswordResetLink');

        $component->assertHasErrors('email');
    }

    public function test_password_reset_requires_existing_user(): void
    {
        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', 'nonexistent@plv.edu.ph')
            ->call('sendPasswordResetLink');

        // Laravel Password broker usually returns generic success or failure message
        // depending on configuration. Our component adds error if status is not RESET_LINK_SENT.
        $component->assertHasErrors('email');
    }

    public function test_password_reset_requires_plv_email_domain(): void
    {
        $component = Volt::test('pages.auth.forgot-password')
            ->set('email', 'user@gmail.com')
            ->call('sendPasswordResetLink');

        $component->assertHasErrors('email');
    }
}
