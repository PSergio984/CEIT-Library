<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FormValidationTest extends TestCase
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

    /** @test - TC081: Form Validation - Required Fields */
    public function required_field_validation_works_on_forms()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Attempt to create academic paper without required fields
        $component = Volt::test('pages.admin.academic-papers.academic-paper-form')
            ->set('form.title', '') // Empty title
            ->set('form.publication_year', '')
            ->set('form.paper_type', '');

        $component->call('store');
        $component->assertHasErrors(['form.title', 'form.publication_year', 'form.paper_type']);

        // Fill all required fields
        $component = Volt::test('pages.admin.academic-papers.academic-paper-form')
            ->set('form.title', 'Test Paper')
            ->set('form.publication_year', 2024)
            ->set('form.paper_type', 'Thesis')
            ->set('form.research_adviser_id', ResearchAdviser::first()->id)
            ->set('form.technical_adviser_id', TechnicalAdviser::first()->id)
            ->set('form.department', 'Computer Science')
            ->set('form.dean_id', Dean::first()->id)
            ->set('form.author_ids', [Author::first()->id])
            ->set('form.number_of_copies', 1);

        $component->call('store');
        $component->assertHasNoErrors();
    }
}

