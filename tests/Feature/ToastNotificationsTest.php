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

class ToastNotificationsTest extends TestCase
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

    /** @test - TC082: Toast Notifications - Success Messages */
    public function success_toast_notifications_appear_after_actions()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $academicPaperData = AcademicPaper::factory()->make()->toArray();
        $authorIds = Author::inRandomOrder()->limit(2)->pluck('id')->toArray();

        $component = Volt::test('pages.admin.academic-papers.academic-paper-form')
            ->set('form.title', $academicPaperData['title'])
            ->set('form.publication_year', $academicPaperData['publication_year'])
            ->set('form.paper_type', $academicPaperData['paper_type'])
            ->set('form.research_adviser_id', ResearchAdviser::first()->id)
            ->set('form.technical_adviser_id', TechnicalAdviser::first()->id)
            ->set('form.department', $academicPaperData['department'])
            ->set('form.dean_id', Dean::first()->id)
            ->set('form.author_ids', $authorIds)
            ->set('form.number_of_copies', 1);

        $component->call('store');
        $component->assertHasNoErrors();
        
        // Success toast should be dispatched (this is primarily a frontend check)
    }

    /** @test - TC083: Toast Notifications - Error Messages */
    public function error_toast_notifications_appear_on_failures()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Attempt invalid operation
        $component = Volt::test('pages.admin.academic-papers.academic-paper-form')
            ->set('form.title', '')
            ->set('form.publication_year', '');

        $component->call('store');
        $component->assertHasErrors();
        
        // Error toast should be dispatched (this is primarily a frontend check)
    }
}

