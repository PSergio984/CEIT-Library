<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Livewire\Pages\Admin\CreateAcademicPaper;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorManagementTest extends TestCase
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

    /** @test - TC076: Author Management - Add New Author */
    #[Test]
    public function new_author_can_be_added_to_academic_paper()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create new author
        $author = Author::factory()->create(['name' => 'New Author']);

        $academicPaperData = AcademicPaper::factory()->make()->toArray();
        $authorIds = Author::inRandomOrder()->limit(2)->pluck('id')->toArray();
        $authorIds[] = $author->id; // Add new author

        Livewire::actingAs($admin)
            ->test(CreateAcademicPaper::class)
            ->set('form.title', $academicPaperData['title'])
            ->set('form.publication_year', $academicPaperData['publication_year'])
            ->set('form.paper_type', $academicPaperData['paper_type'])
            ->set('form.research_adviser_id', ResearchAdviser::first()->id)
            ->set('form.technical_adviser_id', TechnicalAdviser::first()->id)
            ->set('form.department', $academicPaperData['department'])
            ->set('form.dean_id', Dean::first()->id)
            ->set('form.author_ids', $authorIds)
            ->set('form.number_of_copies', 1)
            ->call('save')
            ->assertHasNoErrors();

        $paper = AcademicPaper::where('title', $academicPaperData['title'])->first();
        $this->assertTrue($paper->authors->contains($author));
    }
}
