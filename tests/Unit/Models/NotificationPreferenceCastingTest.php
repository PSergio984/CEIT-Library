<?php

namespace Tests\Unit\Models;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceCastingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_casts_notification_fields_to_boolean()
    {
        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'test',
            'email' => 1,
            'push' => 0,
            'in_app' => 1,
        ]);

        $freshPreference = $preference->fresh();

        $this->assertIsBool($freshPreference->email, 'Email should be a boolean');
        $this->assertIsBool($freshPreference->push, 'Push should be a boolean');
        $this->assertIsBool($freshPreference->in_app, 'In-app should be a boolean');

        $this->assertTrue($freshPreference->email);
        $this->assertFalse($freshPreference->push);
        $this->assertTrue($freshPreference->in_app);
    }
}
