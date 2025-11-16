<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Inventory;
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

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('create')
            ->set('form.title', 'Test Academic Paper')
            ->set('form.publication_year', 2024)
            ->set('form.paper_type', 'Thesis')
            ->set('form.department', 'Information Technology')
            ->set('form.research_project_adviser', 'Dr. John Doe')
            ->set('form.dean', 'Dean Jane Smith')
            ->set('form.author_names', ['Author One', 'Author Two'])
            ->set('form.number_of_copies', 3)
            ->call('saveAcademicPaper')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Test Academic Paper',
            'publication_year' => 2024,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
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

        $paper = AcademicPaper::factory()->create([
            'title' => 'Original Title',
            'research_project_adviser' => 'Dr. Original',
            'dean' => 'Original Dean',
        ]);

        $author1 = Author::factory()->create(['name' => 'Original Author']);
        $paper->authors()->attach($author1->id);

        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 1]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'copy_number' => 2]);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('edit', $paper->id)
            ->set('form.title', 'Updated Title')
            ->set('form.author_names', ['New Author'])
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

        $paper = AcademicPaper::factory()->create([
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

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

        $paper = AcademicPaper::factory()->create([
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Available', 'copy_number' => 1]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Available', 'copy_number' => 2]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Borrowed', 'copy_number' => 3]);
        Inventory::factory()->create(['academic_paper_id' => $paper->id, 'status' => 'Borrowed', 'copy_number' => 4]);

        Livewire::test(AdminAcademicPaperIndex::class)
            ->call('edit', $paper->id)
            ->set('form.number_of_copies', 2)
            ->call('saveAcademicPaper')
            ->assertHasErrors(['form.number_of_copies']);
    }
}
