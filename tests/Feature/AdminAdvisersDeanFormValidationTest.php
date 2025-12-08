<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAdvisersDeans;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAdvisersDeanFormValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student', 'description' => 'Student']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian', 'description' => 'Librarian']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'description' => 'Admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);

        // Create admin user
        $this->admin = User::factory()->create(['role_id' => $superAdminRole->id]);
    }

    /** @test */
    public function create_form_is_valid_when_name_meets_requirements(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('name', '')
            ->assertSet('editingId', null)
            ->set('name', 'John Doe')
            ->assertSet('name', 'John Doe');

        // Verify the component's isFormValid computed property
        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', 'John Doe');

        $this->assertTrue($component->get('isFormValid'));
    }

    /** @test */
    public function create_form_is_invalid_when_name_is_empty(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '');

        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function create_form_is_invalid_when_name_is_too_short(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', 'A');

        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function create_form_is_invalid_when_name_has_less_than_2_letters(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '12345');

        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function edit_form_is_dirty_when_name_changes(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->assertSet('showEditModal', true)
            ->assertSet('editingId', $adviserId)
            ->assertSet('name', 'Original Name')
            ->assertSet('originalName', 'Original Name');

        // Form should not be dirty initially
        $this->assertFalse($component->get('isFormDirty'));

        // Change the name
        $component->set('name', 'Modified Name');

        // Form should now be dirty
        $this->assertTrue($component->get('isFormDirty'));
    }

    /** @test */
    public function edit_form_is_not_dirty_when_name_is_same_as_original(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'Modified Name')
            ->set('name', 'Original Name'); // Change back to original

        // Form should not be dirty when back to original
        $this->assertFalse($component->get('isFormDirty'));
    }

    /** @test */
    public function edit_form_is_not_dirty_when_only_whitespace_differs(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', '  Original Name  '); // Add whitespace

        // Form should not be dirty (trimmed comparison)
        $this->assertFalse($component->get('isFormDirty'));
    }

    /** @test */
    public function edit_form_is_valid_when_dirty_and_name_meets_requirements(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'New Valid Name');

        // Form should be valid (dirty AND valid name)
        $this->assertTrue($component->get('isFormValid'));
        $this->assertTrue($component->get('isFormDirty'));
    }

    /** @test */
    public function edit_form_is_invalid_when_not_dirty(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId);

        // Form should be invalid (not dirty, even though name is valid)
        $this->assertFalse($component->get('isFormValid'));
        $this->assertFalse($component->get('isFormDirty'));
    }

    /** @test */
    public function edit_form_is_invalid_when_dirty_but_name_is_too_short(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'A'); // Too short

        // Form should be dirty but invalid
        $this->assertTrue($component->get('isFormDirty'));
        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function original_name_persists_across_livewire_requests(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->assertSet('originalName', 'Original Name');

        // Simulate multiple property updates (each triggers a request)
        $component->set('name', 'Changed Once');
        $this->assertEquals('Original Name', $component->get('originalName'));

        $component->set('name', 'Changed Twice');
        $this->assertEquals('Original Name', $component->get('originalName'));

        $component->set('name', 'Changed Three Times');
        $this->assertEquals('Original Name', $component->get('originalName'));

        // Original name should persist
        $this->assertTrue($component->get('isFormDirty'));
    }

    /** @test */
    public function can_save_valid_create_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openCreateModal')
            ->set('name', 'New Research Adviser')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $this->assertDatabaseHas('research_advisers', [
            'name' => 'New Research Adviser',
        ]);
    }

    /** @test */
    public function can_save_valid_edit_form(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('research_advisers', [
            'id' => $adviserId,
            'name' => 'Updated Name',
        ]);
    }

    /** @test */
    public function close_modals_resets_form_state(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'Modified Name')
            ->call('closeModals')
            ->assertSet('showEditModal', false)
            ->assertSet('name', '')
            ->assertSet('editingId', null)
            ->assertSet('originalName', null);
    }

    /** @test */
    public function works_with_different_tabs(): void
    {
        // Test technical advisers tab
        $technicalAdviserId = DB::table('technical_advisers')->insertGetId([
            'name' => 'Technical Adviser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'technical')
            ->call('openEditModal', $technicalAdviserId)
            ->assertSet('name', 'Technical Adviser')
            ->set('name', 'Updated Technical Adviser');

        $this->assertTrue($component->get('isFormDirty'));
        $this->assertTrue($component->get('isFormValid'));

        // Test deans tab
        $deanId = DB::table('deans')->insertGetId([
            'name' => 'Dean Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'deans')
            ->call('openEditModal', $deanId)
            ->assertSet('name', 'Dean Name')
            ->set('name', 'Updated Dean');

        $this->assertTrue($component->get('isFormDirty'));
        $this->assertTrue($component->get('isFormValid'));

        // Test authors tab
        $authorId = DB::table('authors')->insertGetId([
            'name' => 'Author Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'authors')
            ->call('openEditModal', $authorId)
            ->assertSet('name', 'Author Name')
            ->set('name', 'Updated Author');

        $this->assertTrue($component->get('isFormDirty'));
        $this->assertTrue($component->get('isFormValid'));
    }

    /** @test */
    public function real_time_validation_triggers_on_property_update(): void
    {
        // Test that validation errors appear when setting invalid name
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '1') // Invalid: only 1 character and no letters
            ->assertHasErrors('name');
    }

    /** @test */
    public function validation_errors_clear_when_valid_name_entered(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '1') // Invalid
            ->assertHasErrors('name')
            ->set('name', 'John Doe') // Valid
            ->assertHasNoErrors('name');
    }

    /** @test */
    public function closing_modal_clears_validation_errors(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '1') // Invalid - triggers error
            ->assertHasErrors('name')
            ->call('closeModals')
            ->assertHasNoErrors('name');
    }

    /** @test */
    public function opening_create_modal_after_edit_with_errors_starts_fresh(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Open edit modal, trigger validation error, close and reopen create modal
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', '1') // Invalid
            ->assertHasErrors('name')
            ->call('closeModals')
            ->call('openCreateModal')
            ->assertHasNoErrors('name')
            ->assertSet('name', '');
    }

    /** @test */
    public function edit_form_is_invalid_with_numbers_only(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', '12345'); // Numbers only - invalid

        // Form should be dirty but invalid (isFormValid requires at least 2 letters)
        $this->assertTrue($component->get('isFormDirty'));
        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function edit_form_is_invalid_with_single_character(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', 'J'); // Single character - invalid

        // Form should be dirty but invalid
        $this->assertTrue($component->get('isFormDirty'));
        $this->assertFalse($component->get('isFormValid'));
    }

    /** @test */
    public function create_form_validation_rejects_special_characters_only(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->call('openCreateModal')
            ->set('name', '---') // Special characters only
            ->assertHasErrors('name');
    }

    /** @test */
    public function opening_edit_modal_after_create_with_errors_starts_fresh(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Open create modal, trigger validation error, close and open edit modal
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openCreateModal')
            ->set('name', '123') // Invalid - numbers only
            ->assertHasErrors('name')
            ->call('closeModals')
            ->call('openEditModal', $adviserId)
            ->assertHasNoErrors('name')
            ->assertSet('name', 'Original Name');
    }

    /** @test */
    public function opening_edit_modal_clears_previous_edit_modal_errors(): void
    {
        // Create two research adviser entries
        $adviserId1 = DB::table('research_advisers')->insertGetId([
            'name' => 'First Adviser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adviserId2 = DB::table('research_advisers')->insertGetId([
            'name' => 'Second Adviser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Open first edit modal, trigger error, close and open second edit modal
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId1)
            ->set('name', '@@@') // Invalid
            ->assertHasErrors('name')
            ->call('closeModals')
            ->call('openEditModal', $adviserId2)
            ->assertHasNoErrors('name')
            ->assertSet('name', 'Second Adviser');
    }

    /** @test */
    public function opening_create_modal_clears_all_form_state_including_errors(): void
    {
        // Create a research adviser entry
        $adviserId = DB::table('research_advisers')->insertGetId([
            'name' => 'Original Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Open edit modal with errors, then open create modal directly (without closeModals)
        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'research')
            ->call('openEditModal', $adviserId)
            ->set('name', '!!!')
            ->assertHasErrors('name')
            ->call('openCreateModal')
            ->assertHasNoErrors('name')
            ->assertSet('name', '')
            ->assertSet('editingId', null)
            ->assertSet('originalName', null)
            ->assertSet('showCreateModal', true)
            ->assertSet('showEditModal', false);
    }
}
