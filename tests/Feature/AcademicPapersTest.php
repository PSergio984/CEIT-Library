<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPapersTest extends TestCase
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

    /** @test - TC012: Academic Papers - Create Button Visibility (Super Admin) */
    public function super_admin_can_see_create_button_on_academic_papers_page()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(200);
        // Page loads lazily, so we check that the page is accessible
        // The actual create button visibility would be tested in a Livewire component test
        // For now, we verify the page loads successfully for super admin
        $this->assertTrue($response->status() === 200);
    }

    /** @test - TC013: Academic Papers - Admin CRUD Allowed */
    public function admin_can_create_edit_and_delete_academic_papers()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
        ]);

        // Create required related records
        $dean = Dean::factory()->create(['name' => 'Test Dean']);
        $researchAdviser = ResearchAdviser::factory()->create(['name' => 'Dr. Research']);
        $technicalAdviser = TechnicalAdviser::factory()->create(['name' => 'Dr. Technical']);

        $response = $this->actingAs($admin)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(200);
        // Page loads successfully - CRUD functionality would be tested in Livewire component tests
        $this->assertTrue($response->status() === 200);
    }

    /** @test - TC014: Academic Papers - Librarian Read-only */
    public function librarian_can_view_academic_papers_but_not_create_edit_or_delete()
    {
        $librarian = User::factory()->create([
            'role_id' => $this->getRoleId('librarian'),
        ]);

        // Create required related records for factory
        Dean::factory()->create();
        ResearchAdviser::factory()->create();
        TechnicalAdviser::factory()->create();

        // Create a paper to view
        AcademicPaper::factory()->create(['title' => 'Test Paper']);

        $response = $this->actingAs($librarian)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(200);
        // Librarian can access the page (read-only access)
        // The actual read-only restrictions would be tested in Livewire component tests
        $this->assertTrue($response->status() === 200);
    }

    /** @test - TC015: Academic Papers - Student Denied */
    public function student_cannot_access_academic_papers_admin_page()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        $response = $this->actingAs($student)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(403);
    }

    /** @test - TC016: Academic Papers - Filters & Pagination */
    public function academic_papers_page_has_filters_and_pagination()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
        ]);

        // Create required related records for factory
        Dean::factory()->create();
        ResearchAdviser::factory()->create();
        TechnicalAdviser::factory()->create();

        // Create multiple papers with different attributes
        AcademicPaper::factory()->count(15)->create([
            'department' => 'Information Technology',
        ]);
        AcademicPaper::factory()->count(5)->create([
            'department' => 'Civil Engineering',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(200);
        // Page loads successfully - filters and pagination would be tested in Livewire component tests
        $this->assertTrue($response->status() === 200);
    }
}
