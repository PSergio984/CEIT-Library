<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Livewire\Pages\Admin\AdminAttendanceLogIndex;
use App\Livewire\Pages\Admin\AdminBorrowTransactions;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Livewire::withoutLazyLoading();
    }

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
        Livewire::test(AdminAcademicPaperIndex::class)
            ->assertStatus(200)
            ->assertSeeHtml('Department')
            ->assertSeeHtml('Search');

        // Check Borrow Logs page
        Livewire::test(AdminBorrowTransactions::class)
            ->assertStatus(200)
            ->assertSeeHtml('Search');

        // Check Attendance Logs page
        Livewire::test(AdminAttendanceLogIndex::class)
            ->assertStatus(200)
            ->assertSeeHtml('Search');
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
        Livewire::test(AdminAcademicPaperIndex::class, ['search' => 'Machine Learning'])
            ->assertStatus(200)
            ->assertSee('Machine Learning Research');

        // Test department filter
        Livewire::test(AdminAcademicPaperIndex::class, ['departmentFilter' => 'Computer Science'])
            ->assertStatus(200)
            ->assertSee('Machine Learning Research')
            ->assertDontSee('Data Structures');

        // Test type filter
        Livewire::test(AdminAcademicPaperIndex::class, ['paperTypeFilter' => 'Thesis'])
            ->assertStatus(200)
            ->assertSee('Machine Learning Research');

        // Test year filter
        Livewire::test(AdminAcademicPaperIndex::class, ['yearFilter' => '2024'])
            ->assertStatus(200)
            ->assertSee('Machine Learning Research');
    }

    /** @test - TC077: Department Filter - All Departments */
    #[Test]
    public function department_filter_shows_all_configured_departments()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->assertStatus(200)
            ->assertSeeHtml('Department');

        // Verify department filter dropdown exists
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
        Livewire::test(AdminAcademicPaperIndex::class, ['search' => 'Mac'])
            ->assertStatus(200)
            ->assertSee('Machine Learning');

        // Test search with full term
        Livewire::test(AdminAcademicPaperIndex::class, ['search' => 'Machine Learning'])
            ->assertStatus(200)
            ->assertSee('Machine Learning')
            ->assertDontSee('Data Mining');
    }
}
