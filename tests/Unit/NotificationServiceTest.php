<?php

namespace Tests\Unit;

use App\Jobs\SendPushNotificationJob;
use App\Mail\GenericNotification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_it_creates_an_in_app_notification_always()
    {
        $user = User::factory()->create();
        $service = new NotificationService;

        $service->notify(
            $user,
            'reminders',
            'Test Title',
            'Test Message',
            ['key' => 'value']
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Test Title',
            'message' => 'Test Message',
            'type' => 'reminders',
        ]);
    }

    /** @test */
    public function test_it_routes_notifications_to_email_when_enabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => true,
            'push' => false,
            'in_app' => true,
        ]);

        $service = new NotificationService;
        $service->notify($user, 'reminders', 'Email Title', 'Email Message');

        Mail::assertQueued(GenericNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /** @test */
    public function test_it_does_not_route_to_email_when_disabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => false,
            'push' => false,
            'in_app' => true,
        ]);

        $service = new NotificationService;
        $service->notify($user, 'reminders', 'No Email Title', 'No Email Message');

        Mail::assertNothingOutgoing();
    }

    /** @test */
    public function test_it_routes_notifications_to_push_when_enabled()
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => false,
            'push' => true,
            'in_app' => true,
        ]);

        $service = new NotificationService;
        $service->notify($user, 'reminders', 'Push Title', 'Push Message');

        Queue::assertPushed(SendPushNotificationJob::class, function ($job) use ($user) {
            return $job->user->id === $user->id && $job->notification->title === 'Push Title';
        });
    }

    /** @test */
    public function test_it_does_not_route_to_push_when_disabled()
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => false,
            'push' => false,
            'in_app' => true,
        ]);

        $service = new NotificationService;
        $service->notify($user, 'reminders', 'No Push Title', 'No Push Message');

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }
}
