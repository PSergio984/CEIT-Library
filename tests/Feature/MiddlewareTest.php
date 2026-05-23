<?php

namespace Tests\Feature;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
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
    #[Test]
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
        $this->get(route('admin.manage-roles'))->assertStatus(302); // Redirected by global exception handler
    }

    /** @test - TC042: Credit Score Middleware - Access Control */
    #[Test]
    public function credit_score_middleware_enforces_minimum_score_requirements()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'credit_score' => 20, // Low credit score
        ]);

        $this->actingAs($student);

        // Attempt to access a page that requires minimum credit score
        // Accessing academic papers index should be blocked by CheckCreditScore middleware
        $this->get(route('academic-paper.index'))->assertStatus(403);
    }

    /** @test - TC075: Middleware - Guest Only Routes */
    #[Test]
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

    /** @test - TC076: Account Status Middleware - Suspended Account Access */
    #[Test]
    public function suspended_users_are_logged_out_and_redirected()
    {
        $user = User::factory()->create([
            'account_status' => 'suspended',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('student.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $response->assertSessionHas('error', 'Your account has been suspended. Please contact the librarian.');
    }

    /** @test - TC077: Global Authorization Exception - Redirection with Toast */
    #[Test]
    public function unauthorized_access_redirects_with_toast()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $this->actingAs($student);

        // Accessing admin only route
        $response = $this->get(route('admin.manage-roles'));

        Log::info('Test expected redirect', ['expected' => route('student.dashboard')]);

        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHas('mary.toast');
    }
}
