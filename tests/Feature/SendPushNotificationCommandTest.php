<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendPushNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_if_user_not_found(): void
    {
        $this->artisan('app:send-push-notification', ['user' => '999'])
            ->expectsOutput('User not found: 999')
            ->assertExitCode(1);
    }

    public function test_it_sends_notification_by_user_id(): void
    {
        $user = User::factory()->create([
            'role_id' => 1,
        ]);

        $this->artisan('app:send-push-notification', [
            'user' => (string) $user->id,
            'message' => 'Custom message here',
            '--title' => 'Custom Title',
        ])
            ->expectsOutputToContain("User: {$user->name}")
            ->expectsOutput('Notification sent and saved successfully!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Custom Title',
            'message' => 'Custom message here',
            'type' => 'manual_push',
        ]);
    }

    public function test_it_sends_notification_by_email(): void
    {
        $user = User::factory()->create([
            'role_id' => 1,
            'email' => 'student@plv.edu.ph',
        ]);

        $this->artisan('app:send-push-notification', [
            'user' => 'student@plv.edu.ph',
            'message' => 'Email matched message',
            '--title' => 'Custom Email Title',
        ])
            ->expectsOutputToContain("User: {$user->name}")
            ->expectsOutput('Notification sent and saved successfully!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Custom Email Title',
            'message' => 'Email matched message',
            'type' => 'manual_push',
        ]);
    }
}
