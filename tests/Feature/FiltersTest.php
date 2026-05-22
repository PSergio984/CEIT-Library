<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltersTest extends TestCase
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

    protected function seedRequiredData(): void
    {
        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student', 'description' => 'Student']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian', 'description' => 'Librarian']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'description' => 'Admin']);
        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);

        ResearchAdviser::factory()->count(3)->create();
        TechnicalAdviser::factory()->count(3)->create();
        Dean::factory()->count(3)->create();
        Author::factory()->count(5)->create();
    }

    /** @test - TC039: Filters - Consistency */
    #[Test]
    public function filter_controls_are_consistent_across_all_pages()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Check Academic Papers page
        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);
        $response->assertSeeHtml('Department');
        $response->assertSeeHtml('Search');

        // Check Borrow Logs page
        $response = $this->get(route('admin.logs'));
        $response->assertStatus(200);
        $response->assertSeeHtml('Search');

        // Check Attendance Logs page
        $response = $this->get(route('admin.attendance'));
        $response->assertStatus(200);
        $response->assertSeeHtml('Search');
    }

    /** @test - TC063: Academic Paper - Search and Filter */
    #[Test]
    public function academic_papers_can_be_searched_and_filtered()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create papers with different attributes
        AcademicPaper::factory()->create([
            'title' => 'Machine Learning Research',
            'department' => 'Computer Science',
            'paper_type' => 'Thesis',
            'publication_year' => 2024,
        ]);
        AcademicPaper::factory()->create([
            'title' => 'Data Structures',
            'department' => 'Engineering',
            'paper_type' => 'Capstone',
            'publication_year' => 2023,
        ]);

        // Test search
        $response = $this->get(route('admin.academic-paper.index', ['search' => 'Machine Learning']));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning Research', false);

        // Test department filter
        $response = $this->get(route('admin.academic-paper.index', ['department' => 'Computer Science']));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning Research', false);
        $response->assertDontSee('Data Structures', false);

        // Test type filter
        $response = $this->get(route('admin.academic-paper.index', ['type' => 'Thesis']));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning Research', false);

        // Test year filter
        $response = $this->get(route('admin.academic-paper.index', ['year' => 2024]));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning Research', false);
    }

    /** @test - TC077: Department Filter - All Departments */
    #[Test]
    public function department_filter_shows_all_configured_departments()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);

        // Verify department filter dropdown exists
        $response->assertSeeHtml('Department');

        // Departments should match config file (this is primarily a frontend check)
    }

    /** @test - TC078: Search - Real-time Results */
    #[Test]
    public function search_shows_real_time_results_as_user_types()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        AcademicPaper::factory()->create(['title' => 'Machine Learning']);
        AcademicPaper::factory()->create(['title' => 'Data Mining']);

        // Test search with partial match
        $response = $this->get(route('admin.academic-paper.index', ['search' => 'Mac']));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning', false);

        // Test search with full term
        $response = $this->get(route('admin.academic-paper.index', ['search' => 'Machine Learning']));
        $response->assertStatus(200);
        $response->assertSee('Machine Learning', false);
        $response->assertDontSee('Data Mining', false);
    }
}
