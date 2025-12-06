<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileViewTest extends TestCase
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

    /** @test - TC087: Profile - View User Information */
    public function user_can_view_their_profile_information()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@plv.edu.ph',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('profile'));
        $response->assertStatus(200);
        
        $response->assertSee('John Doe', false);
        $response->assertSee('john.doe@plv.edu.ph', false);
    }
}

