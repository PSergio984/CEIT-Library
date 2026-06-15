<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_a_user_can_have_notification_preferences()
    {
        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => true,
            'push' => true,
            'in_app' => true,
        ]);

        $this->assertCount(1, $user->notificationPreferences);
        $this->assertEquals('reminders', $user->notificationPreferences->first()->category);
        $this->assertTrue((bool) $user->notificationPreferences->first()->email);
    }

    /** @test */
    public function test_notification_preferences_must_be_unique_per_user_and_category()
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => true,
        ]);

        $this->expectException(QueryException::class);

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => false,
        ]);
    }
}
