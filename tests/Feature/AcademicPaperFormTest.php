<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\Inventory;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicPaperFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = User::factory()->create(['role_id' => 3]);
    }

    #[Test]
    public function admin_can_create_a_new_academic_paper()
    {
        $this->actingAs($this->admin);

        $researchAdviser = ResearchAdviser::factory()->create();
        $technicalAdviser = TechnicalAdviser::factory()->create();
        $dean = Dean::factory()->create();
        $authors = Author::factory()->count(2)->create();

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('create')
            ->set('form.title', 'Test Academic Paper')
            ->set('form.publication_year', 2024)
            ->set('form.paper_type', 'Thesis')
            ->set('form.department', 'Information Technology')
            ->set('form.research_adviser_id', $researchAdviser->id)
            ->set('form.technical_adviser_id', $technicalAdviser->id)
            ->set('form.dean_id', $dean->id)
            ->set('form.author_ids', $authors->pluck('id')->toArray())
            ->set('form.number_of_copies', 3)
            ->call('saveAcademicPaper')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Test Academic Paper',
            'publication_year' => 2024,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'dean_id' => $dean->id,
        ]);

        $paper = AcademicPaper::where('title', 'Test Academic Paper')->first();
        $this->assertEquals(2, $paper->authors()->count());
        $this->assertEquals(3, $paper->copies()->count());
    }

    #[Test]
    public function admin_cannot_create_paper_without_required_fields()
    {
        $this->actingAs($this->admin);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('create')
            ->set('form.title', '')
            ->call('saveAcademicPaper')
            ->assertHasErrors(['form.title']);
    }

    #[Test]
    public function admin_can_update_an_existing_academic_paper()
    {
        $this->actingAs($this->admin);

        $researchAdviser = ResearchAdviser::factory()->create();
        $technicalAdviser = TechnicalAdviser::factory()->create();
        $dean = Dean::factory()->create();

        $paper = AcademicPaper::factory()->create([
            'title' => 'Original Title',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'dean_id' => $dean->id,
        ]);

        $author1 = Author::factory()->create(['name' => 'Original Author']);
        $paper->authors()->attach($author1->id);

        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 1]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 2]);

        $newAuthor = Author::factory()->create(['name' => 'New Author']);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('edit', $paper->id)
            ->set('form.title', 'Updated Title')
            ->set('form.author_ids', [$newAuthor->id])
            ->set('form.number_of_copies', 3)
            ->call('saveAcademicPaper')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_papers', ['id' => $paper->id, 'title' => 'Updated Title']);
        $this->assertEquals(3, $paper->fresh()->copies()->count());
    }

    #[Test]
    public function admin_can_increase_number_of_copies()
    {
        $this->actingAs($this->admin);

        // Pre-create required related models for the factory to pick up
        ResearchAdviser::factory()->create();
        TechnicalAdviser::factory()->create();
        Dean::factory()->create();
        Author::factory()->create();

        $paper = AcademicPaper::factory()->create();

        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 1]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 2]);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('edit', $paper->id)
            ->set('form.number_of_copies', 5)
            ->call('saveAcademicPaper')
            ->assertHasNoErrors();

        $this->assertEquals(5, $paper->fresh()->copies()->count());
    }

    #[Test]
    public function it_prevents_decreasing_copies_below_borrowed_count()
    {
        $this->actingAs($this->admin);

        // Pre-create required related models for the factory to pick up
        ResearchAdviser::factory()->create();
        TechnicalAdviser::factory()->create();
        Dean::factory()->create();
        Author::factory()->create();

        $paper = AcademicPaper::factory()->create();

        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Available', 'copy_number' => 1]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Available', 'copy_number' => 2]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Unavailable', 'copy_number' => 3]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Unavailable', 'copy_number' => 4]);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('edit', $paper->id)
            ->set('form.number_of_copies', 1)
            ->call('saveAcademicPaper')
            ->assertHasErrors(['number_of_copies']);
    }
}
