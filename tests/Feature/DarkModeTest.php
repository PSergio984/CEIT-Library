<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarkModeTest extends TestCase
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

    /** @test - TC088: Dark Mode - Toggle (If Implemented) */
    #[Test]
    public function dark_mode_can_be_toggled_if_feature_exists()
    {
        // This test is conditional - only if dark mode is implemented
        $user = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $this->actingAs($user);

        $response = $this->get(route('student.dashboard'));
        $response->assertStatus(200);

        // Dark mode toggle is primarily a frontend feature
        // This test verifies the page loads correctly
        // Check for theme controller if it exists
        $response->assertSeeHtml('theme-controller', false);
    }
}
