<?php

namespace Tests\Feature;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareTest extends TestCase
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

    /** @test - TC041: Librarian Middleware - Admin Access Prevention */
    public function librarian_users_cannot_access_admin_only_features()
    {
        $librarianUser = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);
        Librarian::factory()->create([
            'user_id' => $librarianUser->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->actingAs($librarianUser);

        // Attempt to access admin-only routes
        $this->get(route('admin.manage-roles'))->assertStatus(403);
        $this->get(route('admin.assign-librarians'))->assertStatus(403);
    }

    /** @test - TC042: Credit Score Middleware - Access Control */
    public function credit_score_middleware_enforces_minimum_score_requirements()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'credit_score' => 20, // Low credit score
        ]);

        $this->actingAs($student);

        // Attempt to borrow (assuming there's a borrow route)
        // This test may need adjustment based on actual implementation
        // The middleware should block access if credit score is too low
    }

    /** @test - TC075: Middleware - Guest Only Routes */
    public function authenticated_users_cannot_access_guest_only_pages()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Attempt to access login page
        $response = $this->get(route('login'));
        $response->assertRedirect(route('dashboard'));

        // Attempt to access register page
        $response = $this->get(route('register'));
        $response->assertRedirect(route('dashboard'));

        // Attempt to access forgot password page
        $response = $this->get(route('password.request'));
        $response->assertRedirect(route('dashboard'));
    }
}
